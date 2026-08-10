<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'id_number' => fake()->unique()->numerify('################'),
            'date_of_birth' => fake()->date(),
            'position' => fake()->randomElement(['Kasir', 'Staf Gudang', 'Admin']),
            'department' => null,
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'hired_at' => fake()->date(),
            'is_active' => true,
            'notes' => null,
        ];
    }
}
