<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\CashierShiftCount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashierShiftCount>
 */
class CashierShiftCountFactory extends Factory
{
    protected $model = CashierShiftCount::class;

    public function definition(): array
    {
        $denomination = fake()->randomElement(['1000.00', '2000.00', '5000.00', '10000.00', '20000.00', '50000.00', '100000.00']);
        $quantity = fake()->numberBetween(0, 20);

        return [
            'cashier_shift_id' => CashierShift::factory(),
            'denomination' => $denomination,
            'quantity' => $quantity,
            'subtotal' => bcmul($denomination, (string) $quantity, 2),
        ];
    }
}
