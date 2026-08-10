<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Models\StockTransferLine;
use App\Infrastructure\Persistence\Models\StockTransferLineBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransferLineBatch>
 */
class StockTransferLineBatchFactory extends Factory
{
    protected $model = StockTransferLineBatch::class;

    public function definition(): array
    {
        return [
            'stock_transfer_line_id' => StockTransferLine::factory(),
            'source_stock_batch_id' => StockBatch::factory(),
            'quantity' => 1,
            'unit_cost' => 10_000,
        ];
    }
}
