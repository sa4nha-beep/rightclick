<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Domain\Finance\Enums\CashEntryType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CashEntry>
 */
class CashEntryFactory extends Factory
{
    protected $model = CashEntry::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'entry_type' => CashEntryType::SalePayment,
            'amount' => fake()->randomFloat(2, 10_000, 500_000),
            'reference_type' => 'test',
            'reference_id' => (string) Str::uuid7(),
            'occurred_at' => now(),
            'created_at' => now(),
        ];
    }
}
