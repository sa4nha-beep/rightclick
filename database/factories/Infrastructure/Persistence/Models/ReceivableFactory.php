<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Receivable;
use App\Infrastructure\Persistence\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receivable>
 */
class ReceivableFactory extends Factory
{
    protected $model = Receivable::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'sale_id' => Sale::factory(),
            'partner_id' => Partner::factory(),
            'original_amount' => '100000.00',
            'paid_amount' => '0.00',
            'outstanding_amount' => '100000.00',
            'payment_status' => 'unpaid',
        ];
    }
}
