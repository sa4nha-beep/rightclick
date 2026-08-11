<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Payable;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payable>
 */
class PayableFactory extends Factory
{
    protected $model = Payable::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'purchase_invoice_id' => PurchaseInvoice::factory(),
            'partner_id' => Partner::factory(),
            'original_amount' => '100000.00',
            'paid_amount' => '0.00',
            'outstanding_amount' => '100000.00',
            'payment_status' => 'unpaid',
        ];
    }
}
