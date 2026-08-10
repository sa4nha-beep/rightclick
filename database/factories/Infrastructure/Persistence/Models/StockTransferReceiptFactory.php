<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\StockTransfer;
use App\Infrastructure\Persistence\Models\StockTransferReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransferReceipt>
 */
class StockTransferReceiptFactory extends Factory
{
    protected $model = StockTransferReceipt::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'stock_transfer_id' => StockTransfer::factory(),
        ];
    }
}
