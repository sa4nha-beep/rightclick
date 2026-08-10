<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Infrastructure\Persistence\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.strtoupper(fake()->unique()->bothify('??####')),
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'product_category_id' => ProductCategory::factory(),
            'base_unit_id' => Unit::factory(),
            'selling_price' => fake()->randomFloat(2, 10_000, 20_000_000),
            'is_active' => true,
            'is_serialized' => false,
            'reorder_level' => null,
            'notes' => null,
        ];
    }
}
