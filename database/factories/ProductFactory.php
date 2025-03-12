<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Product;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'description' => fake()->text(),
            'price' => fake()->randomFloat(2, 0, 99999999.99),
            'stock_quantity' => fake()->numberBetween(-10000, 10000),
            'sku' => fake()->regexify('[A-Za-z0-9]{unique}'),
            'status' => fake()->word(),
            'category_id' => Category::factory(),
            'traits' => fake()->word(),
        ];
    }
}
