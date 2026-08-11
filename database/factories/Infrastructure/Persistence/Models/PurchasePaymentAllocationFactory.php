<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Payable;
use App\Infrastructure\Persistence\Models\PurchasePayment;
use App\Infrastructure\Persistence\Models\PurchasePaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchasePaymentAllocation>
 */
class PurchasePaymentAllocationFactory extends Factory
{
    protected $model = PurchasePaymentAllocation::class;

    public function definition(): array
    {
        return [
            'purchase_payment_id' => PurchasePayment::factory(),
            'payable_id' => Payable::factory(),
            'amount' => '10000.00',
        ];
    }
}
