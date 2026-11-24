<?php

namespace App\Http\Controllers;

use App\Domain\Receipts\Services\ReceiptLinkService;
use App\Http\Requests\ReceiptLinkRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Finding receipts to attach, and attaching them to contracts and savings
 * entries. Writes redirect back, since they happen on several pages.
 */
class ReceiptLinkController extends Controller
{
    public function __construct(private ReceiptLinkService $receiptLinkService) {}

    public function search(Request $request): JsonResponse
    {
        $search = $request->string('q')->limit(100)->toString();

        return response()->json([
            'receipts' => $this->receiptLinkService->search($request->user()->id, $search),
        ]);
    }

    public function store(ReceiptLinkRequest $request): RedirectResponse
    {
        $this->receiptLinkService->link($request->receipt(), $request->linkable());

        return back()->with('success', 'Receipt attached.');
    }

    public function destroy(ReceiptLinkRequest $request): RedirectResponse
    {
        $this->receiptLinkService->unlink($request->receipt(), $request->linkable());

        return back()->with('success', 'Receipt detached.');
    }
}
