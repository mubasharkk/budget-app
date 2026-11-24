<?php

namespace App\Http\Controllers;

use App\Domain\Contracts\Services\ContractInvoiceService;
use App\Http\Requests\StoreContractInvoiceRequest;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Invoice files on a contract. They are stored only; nothing about them
 * counts as spending.
 */
class ContractInvoiceController extends Controller
{
    public function __construct(private ContractInvoiceService $invoiceService) {}

    public function store(StoreContractInvoiceRequest $request, Contract $contract): RedirectResponse
    {
        $count = $this->invoiceService->attach($contract, $request->uploadedFiles())->count();

        return back()->with('success', $count === 1 ? 'Invoice added.' : "{$count} invoices added.");
    }

    public function show(Contract $contract, Media $invoice): BinaryFileResponse
    {
        $this->authorize('view', $contract);

        $media = $this->invoiceService->storedFile($invoice) ?? abort(404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->name).'"',
        ]);
    }

    public function destroy(Contract $contract, Media $invoice): RedirectResponse
    {
        $this->authorize('update', $contract);

        $this->invoiceService->delete($invoice);

        return back()->with('success', 'Invoice removed.');
    }
}
