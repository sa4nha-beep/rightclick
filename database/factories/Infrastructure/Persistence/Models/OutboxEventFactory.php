<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboxEvent>
 */
class OutboxEventFactory extends Factory
{
    protected $model = OutboxEvent::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'event_type' => 'test.event',
            'aggregate_type' => 'test',
            'aggregate_id' => (string) Str::uuid7(),
            'payload' => ['sample' => true],
        ];
    }
}
