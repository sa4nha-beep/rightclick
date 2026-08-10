<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'code' => 'SVC-'.strtoupper(fake()->unique()->bothify('???##')),
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'category' => null,
            'price' => fake()->randomFloat(2, 25_000, 1_000_000),
            'is_active' => true,
            'notes' => null,
        ];
    }
}
