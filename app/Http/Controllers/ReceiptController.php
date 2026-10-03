<?php

namespace App\Http\Controllers;

use App\Domain\Categories\Services\CategoryService;
use App\Domain\Receipts\Data\ReceiptListFilters;
use App\Domain\Receipts\Exceptions\ReceiptCannotBeRetried;
use App\Domain\Receipts\Services\ReceiptService;
use App\Domain\Receipts\Services\ReceiptUploadService;
use App\Http\Requests\StoreReceiptRequest;
use App\Http\Requests\UpdateReceiptRequest;
use App\Models\Receipt;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReceiptController extends Controller
{
    public function __construct(
        private ReceiptService $receiptService,
        private ReceiptUploadService $uploadService,
        private CategoryService $categoryService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = ReceiptListFilters::fromArray($request->query());

        return Inertia::render('Receipts/Index', [
            'receipts' => $this->receiptService->paginate($request->user()->id, $filters),
            'filters' => $filters->toArray(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Receipts/Create');
    }

    /**
     * Mobile-first quick capture — minimal UI, instant upload on capture.
     */
    public function scan(): Response
    {
        return Inertia::render('Receipts/Scan');
    }

    public function store(StoreReceiptRequest $request): JsonResponse|RedirectResponse
    {
        $fileCount = $this->uploadService->storeMany(
            $request->user()->id,
            $request->uploadedFiles(),
            $request->input('expense_type', 'personal'),
            $request->kind(),
        )->count();

        $message = $fileCount === 1
            ? 'Receipt uploaded successfully and is being processed.'
            : "{$fileCount} receipts uploaded successfully and are being processed.";

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['message' => $message, 'count' => $fileCount], 201);
        }

        return redirect()->route('receipts.index')->with('success', $message);
    }

    public function show(Receipt $receipt): Response
    {
        $this->authorize('view', $receipt);

        return Inertia::render('Receipts/Show', [
            'receipt' => $this->receiptService->loadForDisplay($receipt),
        ]);
    }

    /**
     * The receipt detail page doubles as the edit form.
     */
    public function edit(Receipt $receipt): Response
    {
        $this->authorize('update', $receipt);

        return Inertia::render('Receipts/Show', [
            'receipt' => $this->receiptService->loadForDisplay($receipt),
        ]);
    }

    public function update(UpdateReceiptRequest $request, Receipt $receipt): RedirectResponse
    {
        $this->receiptService->update($receipt, $request->validated());

        return redirect()->route('receipts.index')
            ->with('success', 'Receipt updated successfully.');
    }

    /**
     * Categories (with subcategories) for select options.
     */
    public function categories(): JsonResponse
    {
        return response()->json($this->categoryService->tree());
    }

    public function retry(Receipt $receipt): RedirectResponse
    {
        $this->authorize('retry', $receipt);

        try {
            $this->receiptService->retry($receipt);
        } catch (ReceiptCannotBeRetried) {
            return redirect()->route('receipts.show', $receipt)
                ->with('error', 'Only failed receipts can be retried.');
        }

        return redirect()->route('receipts.show', $receipt)
            ->with('success', 'Receipt processing has been retried.');
    }

    public function file(Receipt $receipt): BinaryFileResponse
    {
        $this->authorize('view', $receipt);

        $media = $this->receiptService->storedFile($receipt) ?? abort(404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
        ]);
    }

    /**
     * Keep a receipt that was flagged as a duplicate of an earlier upload.
     */
    public function keepDuplicate(Receipt $receipt): RedirectResponse
    {
        $this->authorize('update', $receipt);

        $this->receiptService->keepDuplicate($receipt);

        return back()->with('success', 'Receipt kept.');
    }

    public function destroy(Receipt $receipt): RedirectResponse
    {
        $this->authorize('delete', $receipt);

        try {
            $this->receiptService->delete($receipt);
        } catch (Exception $e) {
            return redirect()->route('receipts.index')
                ->with('error', 'Failed to delete receipt: '.$e->getMessage());
        }

        $previous = url()->previous();
        $cameFromReceipt = in_array($previous, [route('receipts.show', $receipt), route('receipts.edit', $receipt)], true);

        return ($cameFromReceipt ? redirect()->route('receipts.index') : back())
            ->with('success', 'Receipt deleted successfully.');
    }
}
