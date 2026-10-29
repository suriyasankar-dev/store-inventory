<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $products = Product::query()
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], 'page', $validated['page'] ?? null);

        return response()->json($this->paginatedResponse($products));
    }

    public function catalog()
    {
        return response()->json([
            'data' => Product::query()->orderBy('name')->get(),
        ]);
    }

    public function lowStock(Request $request)
    {
        $validated = $request->validate([
            'threshold' => ['sometimes', 'integer', 'min:0'],
        ]);
        $threshold = $validated['threshold'] ?? config('inventory.low_stock_threshold', 5);

        $products = Product::query()
            ->where('stock_on_hand', '<', $threshold)
            ->orderBy('stock_on_hand')
            ->get();

        return response()->json(['data' => $products]);
    }

    private function paginatedResponse($paginator): array
    {
        return [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
