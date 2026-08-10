<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use App\Infrastructure\Persistence\Models\PurchasePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchasePayment>
 */
class PurchasePaymentFactory extends Factory
{
    protected $model = PurchasePayment::class;

    public function definition(): array
    {
        return [
            'purchase_invoice_id' => PurchaseInvoice::factory(),
            'method' => PaymentMethod::Cash->value,
            'amount' => '10000.00',
        ];
    }
}
