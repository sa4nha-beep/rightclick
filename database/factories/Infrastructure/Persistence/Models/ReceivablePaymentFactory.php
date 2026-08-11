<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Infrastructure\Persistence\Models\ReceivablePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivablePayment>
 */
class ReceivablePaymentFactory extends Factory
{
    protected $model = ReceivablePayment::class;

    public function definition(): array
    {
        return [
            'method' => PaymentMethod::Cash->value,
            'amount' => '10000.00',
        ];
    }
}
