<?php

namespace App\Http\Controllers;

use App\Domain\Products\Services\PriceIntelligenceService;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private PriceIntelligenceService $priceIntelligenceService) {}

    /**
     * Product detail page with price history and purchase history.
     */
    public function show(Product $product): Response
    {
        $this->authorize('view', $product);

        return Inertia::render('Products/Show', [
            'product' => $product->load('category'),
        ]);
    }

    /**
     * JSON data for the product detail charts and tables.
     */
    public function data(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return response()->json(Arr::only(
            $this->priceIntelligenceService->productDetail($request->user()->id, $product),
            ['product', 'price_history', 'by_vendor', 'purchases'],
        ));
    }
}
