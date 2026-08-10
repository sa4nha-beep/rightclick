<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'code' => 'CAT-'.strtoupper(fake()->unique()->bothify('????##')),
            'name' => fake()->unique()->word(),
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ];
    }
}
