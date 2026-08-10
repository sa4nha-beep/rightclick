<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\SalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalePayment>
 */
class SalePaymentFactory extends Factory
{
    protected $model = SalePayment::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => 10_000,
        ];
    }
}
