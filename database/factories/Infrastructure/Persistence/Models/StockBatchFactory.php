<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StockBatch>
 */
class StockBatchFactory extends Factory
{
    protected $model = StockBatch::class;

    public function definition(): array
    {
        $qty = fake()->randomFloat(4, 1, 100);

        return [
            'branch_id' => Branch::factory(),
            'product_id' => Product::factory(),
            'received_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'unit_cost' => fake()->randomFloat(2, 10_000, 5_000_000),
            'qty_received' => $qty,
            'qty_remaining' => $qty,
            'reference_type' => 'test',
            'reference_id' => (string) Str::uuid7(),
        ];
    }
}
