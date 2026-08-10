<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockTransfer;
use App\Infrastructure\Persistence\Models\StockTransferLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransferLine>
 */
class StockTransferLineFactory extends Factory
{
    protected $model = StockTransferLine::class;

    public function definition(): array
    {
        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
        ];
    }
}
