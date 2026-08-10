<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseInvoice>
 */
class PurchaseInvoiceFactory extends Factory
{
    protected $model = PurchaseInvoice::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'goods_receipt_id' => GoodsReceipt::factory(),
            'partner_id' => Partner::factory(),
            'invoice_number' => 'INV-'.strtoupper(fake()->unique()->bothify('????##')),
            'invoice_date' => now()->toDateString(),
        ];
    }
}
