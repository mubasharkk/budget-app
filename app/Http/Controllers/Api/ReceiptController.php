<?php

namespace App\Http\Controllers\Api;

use App\Domain\Receipts\Services\ReceiptService;
use App\Domain\Receipts\Services\ReceiptUploadService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function __construct(
        private ReceiptService $receiptService,
        private ReceiptUploadService $uploadService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $receipts = $this->receiptService->paginateForApi(
            $request->user()->id,
            $request->integer('per_page', 20),
        );

        return ReceiptResource::collection($receipts)->response();
    }

    public function store(StoreReceiptRequest $request): JsonResponse
    {
        $receipts = $this->uploadService->storeMany(
            $request->user()->id,
            $request->uploadedFiles(),
            $request->input('expense_type', 'personal'),
            $request->kind(),
        );

        return response()->json([
            'message' => $receipts->count() === 1
                ? 'Receipt uploaded and queued for processing.'
                : "{$receipts->count()} receipts uploaded and queued for processing.",
            'receipts' => ReceiptResource::collection($receipts),
        ], 201);
    }

    public function show(Receipt $receipt): JsonResponse
    {
        $this->authorize('view', $receipt);

        $receipt->load(['items.category', 'items.subcategory']);

        return response()->json([
            'receipt' => new ReceiptResource($receipt),
            'items' => $receipt->items,
        ]);
    }
}
