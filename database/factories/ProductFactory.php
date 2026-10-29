<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => fake()->unique()->bothify('PRD-####'),
            'price' => fake()->randomFloat(2, 1, 1000),
            'tax_percentage' => fake()->randomElement([0, 5, 10, 18]),
            'stock_on_hand' => fake()->numberBetween(0, 100),
        ];
    }
}
