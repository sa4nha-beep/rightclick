<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashierShift>
 */
class CashierShiftFactory extends Factory
{
    protected $model = CashierShift::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'cashier_id' => User::factory(),
            'opening_cash' => 500_000,
        ];
    }
}
