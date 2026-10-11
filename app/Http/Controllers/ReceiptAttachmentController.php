<?php

namespace App\Http\Controllers;

use App\Domain\Receipts\Services\ReceiptAttachmentService;
use App\Http\Requests\StoreReceiptAttachmentRequest;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Additional documents on a receipt (bank payment confirmations etc.). They are
 * stored only; nothing about them counts as spending.
 */
class ReceiptAttachmentController extends Controller
{
    public function __construct(private ReceiptAttachmentService $attachmentService) {}

    public function store(StoreReceiptAttachmentRequest $request, Receipt $receipt): RedirectResponse
    {
        $count = $this->attachmentService->attach($receipt, $request->uploadedFiles())->count();

        return back()->with('success', $count === 1 ? 'Document attached.' : "{$count} documents attached.");
    }

    public function show(Receipt $receipt, Media $attachment): BinaryFileResponse
    {
        $this->authorize('view', $receipt);

        $media = $this->attachmentService->storedFile($attachment) ?? abort(404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->name).'"',
        ]);
    }

    public function destroy(Receipt $receipt, Media $attachment): RedirectResponse
    {
        $this->authorize('update', $receipt);

        $this->attachmentService->delete($attachment);

        return back()->with('success', 'Document removed.');
    }
}
