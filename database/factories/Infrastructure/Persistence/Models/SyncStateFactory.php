<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\SyncState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncState>
 */
class SyncStateFactory extends Factory
{
    protected $model = SyncState::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
        ];
    }
}
