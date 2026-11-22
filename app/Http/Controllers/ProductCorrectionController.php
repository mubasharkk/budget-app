<?php

namespace App\Http\Controllers;

use App\Domain\Products\Exceptions\PackUnitMismatch;
use App\Domain\Products\Services\ProductCorrectionService;
use App\Http\Requests\ProductCorrectionRequest;
use App\Http\Requests\UnmatchedItemRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * The user's fixes to general names and pack sizes.
 */
class ProductCorrectionController extends Controller
{
    public function __construct(private ProductCorrectionService $correctionService) {}

    public function update(ProductCorrectionRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        try {
            $product = $this->correctionService->correct($product, $request->correction());
        } catch (PackUnitMismatch $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['product_id' => $product->id]);
    }

    public function store(UnmatchedItemRequest $request): JsonResponse
    {
        try {
            $product = $this->correctionService->nameUnmatchedItem($request->user(), $request->validated('name'), $request->correction());
        } catch (PackUnitMismatch $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if ($product === null) {
            return response()->json(['message' => 'Those receipt lines are already sorted. Reload the page.'], 404);
        }

        return response()->json(['product_id' => $product->id], 201);
    }
}
