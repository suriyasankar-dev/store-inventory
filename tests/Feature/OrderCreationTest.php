<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_order_calculates_tax_and_deducts_stock(): void
    {
        Queue::fake();
        $product = Product::factory()->create([
            'price' => 10,
            'tax_percentage' => 10,
            'stock_on_hand' => 4,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => ['name' => 'Avery Customer', 'email' => 'avery@example.com'],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '20.00')
            ->assertJsonPath('data.tax', '2.00')
            ->assertJsonPath('data.total', '22.00');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_on_hand' => 2]);
        $this->assertDatabaseHas('customers', ['email' => 'avery@example.com']);
        $this->assertDatabaseCount('orders', 1);
        Queue::assertPushed(SendOrderConfirmation::class);
    }

    public function test_insufficient_stock_rejects_the_order_without_changing_stock(): void
    {
        $product = Product::factory()->create(['stock_on_hand' => 1]);

        $response = $this->postJson('/api/orders', [
            'customer' => ['name' => 'Avery Customer', 'email' => 'avery@example.com'],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_on_hand' => 1]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_duplicate_lines_are_combined_for_stock_validation(): void
    {
        $product = Product::factory()->create(['stock_on_hand' => 1]);

        $response = $this->postJson('/api/orders', [
            'customer' => ['name' => 'Avery Customer', 'email' => 'avery@example.com'],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_on_hand' => 1]);
    }

    public function test_customer_history_and_low_stock_endpoints_return_expected_data(): void
    {
        $customer = Customer::factory()->create(['email' => 'history@example.com']);
        $otherCustomer = Customer::factory()->create(['email' => 'other@example.com']);
        $lowStockProduct = Product::factory()->create(['stock_on_hand' => 2]);
        Product::factory()->create(['stock_on_hand' => 5]);
        Order::create([
            'customer_id' => $customer->id,
            'subtotal' => 10,
            'tax' => 1,
            'total' => 11,
        ]);
        Order::create([
            'customer_id' => $otherCustomer->id,
            'subtotal' => 20,
            'tax' => 2,
            'total' => 22,
        ]);

        $this->getJson('/api/customers/orders?email=history%40example.com')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.email', 'history@example.com');

        $this->getJson('/api/customers/orders')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->getJson('/api/products/low-stock?threshold=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lowStockProduct->id);
    }

    public function test_inventory_and_order_history_use_ten_row_pagination(): void
    {
        $customer = Customer::factory()->create(['email' => 'pages@example.com']);
        Product::factory(21)->create();

        foreach (range(1, 21) as $_) {
            Order::create([
                'customer_id' => $customer->id,
                'subtotal' => 10,
                'tax' => 1,
                'total' => 11,
            ]);
        }

        $this->getJson('/api/products?page=2')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.total', 21);

        $this->getJson('/api/customers/orders?email=pages%40example.com&page=2')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.total', 21);

        $this->getJson('/api/products/catalog')
            ->assertOk()
            ->assertJsonCount(21, 'data');
    }
}
