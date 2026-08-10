<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Domain\Inventory\Enums\StockAdjustmentDirection;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use App\Infrastructure\Persistence\Models\StockAdjustmentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentLine>
 */
class StockAdjustmentLineFactory extends Factory
{
    protected $model = StockAdjustmentLine::class;

    public function definition(): array
    {
        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'product_id' => Product::factory(),
            'direction' => StockAdjustmentDirection::Increase,
            'quantity' => 1,
            'unit_cost' => 10_000,
            'reason' => 'Uji otomatis',
        ];
    }
}
