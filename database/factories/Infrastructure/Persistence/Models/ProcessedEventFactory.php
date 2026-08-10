<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\ProcessedEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProcessedEvent>
 */
class ProcessedEventFactory extends Factory
{
    protected $model = ProcessedEvent::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'branch_id' => Branch::factory(),
            'event_type' => 'test.finalized',
            'aggregate_type' => 'test',
            'aggregate_id' => (string) Str::uuid7(),
            'processed_at' => now(),
            'created_at' => now(),
        ];
    }
}
