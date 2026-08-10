<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockOpname;
use App\Infrastructure\Persistence\Models\StockOpnameLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpnameLine>
 */
class StockOpnameLineFactory extends Factory
{
    protected $model = StockOpnameLine::class;

    public function definition(): array
    {
        return [
            'stock_opname_id' => StockOpname::factory(),
            'product_id' => Product::factory(),
            'system_qty' => 0,
            'counted_qty' => 0,
            'unit_cost' => null,
            'reason' => null,
        ];
    }
}
