<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    protected $model = GoodsReceipt::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'partner_id' => Partner::factory(),
        ];
    }
}
