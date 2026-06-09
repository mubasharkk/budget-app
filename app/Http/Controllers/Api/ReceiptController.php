<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Services\ReceiptUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function __construct(private ReceiptUploadService $uploadService) {}

    public function index(Request $request): JsonResponse
    {
        $receipts = Receipt::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return ReceiptResource::collection($receipts)->response();
    }

    public function store(StoreReceiptRequest $request): JsonResponse
    {
        $receipts = $this->uploadService->storeMany(
            $request->user()->id,
            $request->uploadedFiles(),
        );

        return response()->json([
            'message' => $receipts->count() === 1
                ? 'Receipt uploaded and queued for processing.'
                : "{$receipts->count()} receipts uploaded and queued for processing.",
            'receipts' => ReceiptResource::collection($receipts),
        ], 201);
    }

    public function show(Request $request, Receipt $receipt): JsonResponse
    {
        $this->authorize('view', $receipt);

        $receipt->load(['items.category', 'items.subcategory']);

        return response()->json([
            'receipt' => new ReceiptResource($receipt),
            'items' => $receipt->items,
        ]);
    }
}
