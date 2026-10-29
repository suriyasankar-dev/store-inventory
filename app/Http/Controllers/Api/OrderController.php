<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderServices;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private const PER_PAGE = 10;

    public function store(StoreOrderRequest $request, OrderServices $orderServices)
    {
        $order = $orderServices->createOrder($request->validated());

        SendOrderConfirmation::dispatch($order);

        return response()->json(['data' => $order], 201);
    }

    public function history(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Order::query()
            ->with(['customer', 'items.product'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('email', $validated['email']));

        $orders = $query->paginate(self::PER_PAGE, ['*'], 'page', $validated['page'] ?? null);

        return response()->json([
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ]);
    }

    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $customer = Customer::query()
            ->where('email', $validated['email'])
            ->first(['name', 'email']);

        return response()->json(['data' => $customer]);
    }
}
