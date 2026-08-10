<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\SaleItem;
use App\Infrastructure\Persistence\Models\SaleReturn;
use App\Infrastructure\Persistence\Models\SaleReturnLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnLine>
 */
class SaleReturnLineFactory extends Factory
{
    protected $model = SaleReturnLine::class;

    public function definition(): array
    {
        return [
            'sale_return_id' => SaleReturn::factory(),
            'sale_item_id' => SaleItem::factory(),
            'quantity' => 1,
            'reason' => 'Uji otomatis',
        ];
    }
}
