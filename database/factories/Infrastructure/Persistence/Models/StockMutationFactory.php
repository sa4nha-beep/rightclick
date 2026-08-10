<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Domain\Inventory\Enums\StockMutationType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Models\StockMutation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StockMutation>
 */
class StockMutationFactory extends Factory
{
    protected $model = StockMutation::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'product_id' => Product::factory(),
            'stock_batch_id' => StockBatch::factory(),
            'mutation_type' => StockMutationType::Receipt,
            'quantity' => fake()->randomFloat(4, 1, 50),
            'unit_cost' => fake()->randomFloat(2, 10_000, 5_000_000),
            'reference_type' => 'test',
            'reference_id' => (string) Str::uuid7(),
            'occurred_at' => now(),
            'created_at' => now(),
        ];
    }
}
