<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Domain\Inventory\Enums\StockOpnameType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\StockOpname;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpname>
 */
class StockOpnameFactory extends Factory
{
    protected $model = StockOpname::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'type' => StockOpnameType::Periodic,
        ];
    }
}
