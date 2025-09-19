<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOcr;
use App\Models\Category;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ReceiptController extends Controller
{
    /**
     * Display a listing of receipts
     */
    public function index()
    {
        $receipts = Receipt::with(['items.category', 'items.subcategory'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Append file URLs to each receipt
        $receipts->getCollection()->transform(function ($receipt) {
            return $receipt->append(['file_url', 'public_file_url', 'direct_file_url']);
        });

        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts
        ]);
    }

    /**
     * Show the upload form
     */
    public function create()
    {
        return Inertia::render('Receipts/Create');
    }

    /**
     * Store a newly uploaded receipt
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,heic,webp,pdf|max:15360', // 15MB max
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        // Generate unique filename with date structure
        $year = now()->year;
        $month = now()->format('m');
        $uuid = Str::uuid();
        
        // Convert images to PNG, keep PDFs as-is
        if ($file->getMimeType() === 'application/pdf') {
            $extension = 'pdf';
            $filename = "{$uuid}.{$extension}";
            $path = "receipts/{$year}/{$month}/";
            $storedPath = $file->storeAs($path, $filename, 'public');
        } else {
            // Convert images to PNG
            $extension = 'png';
            $filename = "{$uuid}.{$extension}";
            $path = "receipts/{$year}/{$month}/";
            
            try {
                // Convert image to PNG using Intervention Image
                $imageManager = new ImageManager(new Driver());
                $image = $imageManager->read($file->getRealPath());
                
                // Convert to PNG and save
                $pngData = $image->toPng();
                $storedPath = $path . $filename;
                Storage::disk('public')->put($storedPath, $pngData);
                
                // Update MIME type and file size for PNG
                $mimeType = 'image/png';
                $fileSize = strlen($pngData);
                
                Log::info('Image converted to PNG', [
                    'original_filename' => $originalFilename,
                    'original_mime' => $file->getMimeType(),
                    'converted_filename' => $filename,
                    'file_size' => $fileSize
                ]);
            } catch (\Exception $e) {
                Log::error('Image conversion failed', [
                    'original_filename' => $originalFilename,
                    'error' => $e->getMessage()
                ]);
                
                // Fallback: store original file if conversion fails
                $extension = $file->getClientOriginalExtension();
                $filename = "{$uuid}.{$extension}";
                $storedPath = $file->storeAs($path, $filename, 'public');
            }
        }

        // Create receipt record
        $receipt = Receipt::create([
            'user_id' => Auth::id(),
            'original_filename' => $originalFilename,
            'original_path' => $path,
            'stored_path' => $storedPath,
            'file_type' => $extension,
            'mime' => $mimeType,
            'file_size' => $fileSize,
            'status' => 'pending'
        ]);

        // Dispatch OCR processing job (which will chain to LLM processing)
        ProcessOcr::dispatch($receipt);

        return redirect()->route('receipts.show', $receipt)
            ->with('success', 'Receipt uploaded successfully and is being processed.');
    }

    /**
     * Display the specified receipt
     */
    public function show(Receipt $receipt)
    {
        // Ensure user can only view their own receipts
        if ($receipt->user_id !== Auth::id()) {
            abort(403);
        }

        $receipt->load(['items.category', 'items.subcategory']);

        return Inertia::render('Receipts/Show', [
            'receipt' => $receipt->append(['file_url', 'public_file_url', 'direct_file_url'])
        ]);
    }

    /**
     * Update the specified receipt
     */
    public function update(Request $request, Receipt $receipt)
    {
        // Ensure user can only update their own receipts
        if ($receipt->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'vendor' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
            'total_amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.category_id' => 'nullable|exists:categories,id',
            'items.*.subcategory_id' => 'nullable|exists:categories,id',
        ]);

        // Update receipt
        $receipt->update([
            'vendor' => $request->vendor,
            'currency' => $request->currency,
            'total_amount' => $request->total_amount,
        ]);

        // Update items if provided
        if ($request->has('items')) {
            $receipt->items()->delete();

            foreach ($request->items as $itemData) {
                $receipt->items()->create([
                    'name' => $itemData['name'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => $itemData['total'],
                    'category_id' => $itemData['category_id'] ?? null,
                    'subcategory_id' => $itemData['subcategory_id'] ?? null,
                ]);
            }
        }

        return redirect()->route('receipts.index')
            ->with('success', 'Receipt updated successfully.');
    }

    /**
     * Get categories for select options
     */
    public function categories()
    {
        $categories = Category::with('subcategories')
            ->whereNull('parent_id')
            ->get();

        return response()->json($categories);
    }

    /**
     * Retry processing a failed receipt
     */
    public function retry(Receipt $receipt)
    {
        // Ensure user can only retry their own receipts
        if ($receipt->user_id !== Auth::id()) {
            abort(403);
        }

        if ($receipt->status !== 'failed') {
            return redirect()->route('receipts.show', $receipt)
                ->with('error', 'Only failed receipts can be retried.');
        }

        // Reset status and dispatch job
        $receipt->update([
            'status' => 'pending',
            'error_message' => null
        ]);

        ProcessOcr::dispatch($receipt);

        return redirect()->route('receipts.show', $receipt)
            ->with('success', 'Receipt processing has been retried.');
    }

    /**
     * Serve receipt file directly
     */
    public function file(Receipt $receipt)
    {
        // Ensure user can only access their own receipts
        if ($receipt->user_id !== Auth::id()) {
            abort(403);
        }

        $path = $receipt->stored_path ?: $receipt->original_path;
        $filePath = storage_path('app/public/' . $path);

        if (!file_exists($filePath)) {
            abort(404);
        }

        return response()->file($filePath);
    }

    /**
     * Remove the specified receipt from storage
     */
    public function destroy(Receipt $receipt)
    {
        // Ensure user can only delete their own receipts
        if ($receipt->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            Log::info('Deleting receipt', [
                'receipt_id' => $receipt->id,
                'user_id' => Auth::id(),
                'filename' => $receipt->original_filename
            ]);

            // Delete the physical file
            if ($receipt->fileExists()) {
                $path = $receipt->stored_path ?: $receipt->original_path;
                Storage::disk('public')->delete($path);
                Log::info('File deleted', ['path' => $path]);
            } else {
                Log::warning('File not found for deletion', [
                    'receipt_id' => $receipt->id,
                    'path' => $receipt->stored_path ?: $receipt->original_path
                ]);
            }

            // Delete the receipt record (this will cascade delete items)
            $receipt->delete();

            Log::info('Receipt deleted successfully', ['receipt_id' => $receipt->id]);

            return redirect()->route('receipts.index')
                ->with('success', 'Receipt deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete receipt', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('receipts.index')
                ->with('error', 'Failed to delete receipt: ' . $e->getMessage());
        }
    }
}
