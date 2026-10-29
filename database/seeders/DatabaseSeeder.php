<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Customer::factory(10)->create();
        Product::factory(17)->create();
        Product::factory()->create([
            'name' => 'Counter Bread',
            'code' => 'DEMO-BREAD',
            'stock_on_hand' => 4,
        ]);
        Product::factory()->create([
            'name' => 'Fresh Milk 1L',
            'code' => 'DEMO-MILK',
            'stock_on_hand' => 3,
        ]);
        Product::factory()->create([
            'name' => 'Farm Eggs 12ct',
            'code' => 'DEMO-EGGS',
            'stock_on_hand' => 2,
        ]);
    }
}
