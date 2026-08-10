<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\GoodsReceiptLine;
use App\Infrastructure\Persistence\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptLine>
 */
class GoodsReceiptLineFactory extends Factory
{
    protected $model = GoodsReceiptLine::class;

    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'product_id' => Product::factory(),
            'quantity' => '1.0000',
            'unit_cost' => '10000.00',
        ];
    }
}
