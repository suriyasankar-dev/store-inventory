<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderServices
{
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $quantities = collect($data['items'])
                ->groupBy('product_id')
                ->map(fn ($lines): int => (int) $lines->sum('quantity'));

            $products = Product::query()
                ->whereIn('id', $quantities->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $quantities->count()) {
                throw ValidationException::withMessages([
                    'items' => 'One or more selected products are no longer available.',
                ]);
            }

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get((int) $productId);

                if ($product->stock_on_hand < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$product->name}. Available: {$product->stock_on_hand}.",
                    ]);
                }
            }

            $customer = Customer::firstOrCreate(
                ['email' => $data['customer']['email']],
                ['name' => $data['customer']['name']],
            );

            $subtotalCents = 0;
            $taxCents = 0;
            $orderLines = [];

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get((int) $productId);
                $lineSubtotalCents = (int) round((float) $product->price * 100) * $quantity;
                $taxRateBasisPoints = (int) round((float) $product->tax_percentage * 100);
                $lineTaxCents = (int) round($lineSubtotalCents * $taxRateBasisPoints / 10000);
                $lineTotalCents = $lineSubtotalCents + $lineTaxCents;

                $subtotalCents += $lineSubtotalCents;
                $taxCents += $lineTaxCents;
                $orderLines[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'tax_percentage' => $product->tax_percentage,
                    'subtotal' => $this->formatAmount($lineSubtotalCents),
                    'tax' => $this->formatAmount($lineTaxCents),
                    'total' => $this->formatAmount($lineTotalCents),
                ];

                $product->stock_on_hand -= $quantity;
                $product->save();
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'subtotal' => $this->formatAmount($subtotalCents),
                'tax' => $this->formatAmount($taxCents),
                'total' => $this->formatAmount($subtotalCents + $taxCents),
            ]);

            $order->items()->createMany($orderLines);

            return $order->load(['customer', 'items.product']);
        });
    }

    private function formatAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
