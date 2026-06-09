<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\PriceIntelligenceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private PriceIntelligenceService $priceIntelligenceService) {}

    /**
     * Product detail page with price history and purchase history.
     */
    public function show(Request $request, Product $product)
    {
        $this->authorize('view', $product);

        return Inertia::render('Products/Show', [
            'product' => $product->load('category'),
        ]);
    }

    /**
     * JSON data for the product detail charts and tables.
     */
    public function data(Request $request, Product $product)
    {
        $this->authorize('view', $product);

        $detail = $this->priceIntelligenceService->productDetail($request->user()->id, $product);

        return response()->json([
            'product' => $detail['product'],
            'price_history' => $detail['price_history'],
            'by_vendor' => $detail['by_vendor'],
            'purchases' => $detail['purchases'],
        ]);
    }
}
