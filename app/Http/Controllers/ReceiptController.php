<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReceiptRequest;
use App\Jobs\ProcessReceipt;
use App\Models\Category;
use App\Models\Receipt;
use App\Services\ReceiptUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    public function __construct(private ReceiptUploadService $uploadService) {}

    /**
     * Display a listing of receipts
     */
    public function index(Request $request)
    {
        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->integer('per_page', 50);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 50;
        }

        $allowedSorts = ['created_at', 'receipt_date', 'total_amount', 'vendor'];
        $sort = in_array($request->get('sort'), $allowedSorts, true) ? $request->get('sort') : 'created_at';
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $status = in_array($request->get('status'), ['pending', 'processed', 'failed'], true)
            ? $request->get('status')
            : null;
        $search = trim((string) $request->get('search')) ?: null;

        $receipts = Receipt::with(['items.category', 'items.subcategory'])
            ->where('user_id', Auth::id())
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('vendor', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        // Append file URLs to each receipt
        $receipts->getCollection()->transform(function ($receipt) {
            return $receipt->append(['file_url', 'public_file_url', 'direct_file_url']);
        });

        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
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
     * Mobile-first quick capture — minimal UI, instant upload on capture.
     */
    public function scan()
    {
        return Inertia::render('Receipts/Scan');
    }

    /**
     * Store a newly uploaded receipt
     */
    public function store(StoreReceiptRequest $request)
    {
        $createdReceipts = $this->uploadService->storeMany(
            Auth::id(),
            $request->uploadedFiles(),
            $request->input('expense_type', 'personal'),
        );

        $fileCount = $createdReceipts->count();
        $message = $fileCount === 1
            ? 'Receipt uploaded successfully and is being processed.'
            : "{$fileCount} receipts uploaded successfully and are being processed.";

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'count' => $fileCount,
            ], 201);
        }

        return redirect()->route('receipts.index')
            ->with('success', $message);
    }

    /**
     * Display the specified receipt
     */
    public function show(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        $receipt->load(['items.category', 'items.subcategory']);

        return Inertia::render('Receipts/Show', [
            'receipt' => $receipt->append(['file_url', 'public_file_url', 'direct_file_url']),
        ]);
    }

    /**
     * Update the specified receipt
     */
    public function update(Request $request, Receipt $receipt)
    {
        $this->authorize('update', $receipt);

        $request->validate([
            'vendor' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:255',
            'currency' => 'nullable|string|in:EUR,USD,INR,PKR,TRY,GBP',
            'total_amount' => 'required|numeric|min:0',
            'receipt_date' => 'required|date|before_or_equal:now',
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
            'receipt_number' => $request->receipt_number,
            'currency' => $request->currency,
            'total_amount' => $request->total_amount,
            'receipt_date' => $request->receipt_date,
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
        $this->authorize('retry', $receipt);

        if ($receipt->status !== 'failed') {
            return redirect()->route('receipts.show', $receipt)
                ->with('error', 'Only failed receipts can be retried.');
        }

        // Reset status and dispatch job
        $receipt->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        ProcessReceipt::dispatch($receipt);

        return redirect()->route('receipts.show', $receipt)
            ->with('success', 'Receipt processing has been retried.');
    }

    /**
     * Serve receipt file directly
     */
    public function file(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        if (! $receipt->fileExists()) {
            abort(404);
        }

        return response()->file($receipt->file_path);
    }

    /**
     * Remove the specified receipt from storage
     */
    public function destroy(Receipt $receipt)
    {
        $this->authorize('delete', $receipt);

        try {
            Log::info('Deleting receipt', [
                'receipt_id' => $receipt->id,
                'user_id' => Auth::id(),
                'filename' => $receipt->original_filename,
            ]);

            // Delete the receipt record; media-library removes the attached file
            // and the items cascade.
            $receipt->delete();

            Log::info('Receipt deleted successfully', ['receipt_id' => $receipt->id]);

            return redirect()->route('receipts.index')
                ->with('success', 'Receipt deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete receipt', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('receipts.index')
                ->with('error', 'Failed to delete receipt: '.$e->getMessage());
        }
    }
}
