<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Receivable;
use App\Infrastructure\Persistence\Models\ReceivablePayment;
use App\Infrastructure\Persistence\Models\ReceivablePaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivablePaymentAllocation>
 */
class ReceivablePaymentAllocationFactory extends Factory
{
    protected $model = ReceivablePaymentAllocation::class;

    public function definition(): array
    {
        return [
            'receivable_payment_id' => ReceivablePayment::factory(),
            'receivable_id' => Receivable::factory(),
            'amount' => '10000.00',
        ];
    }
}
