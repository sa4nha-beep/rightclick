<?php

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            // `users.default_branch_id` NOT NULL (T1.4) — sebelum T4.1 tidak
            // ada factory lain yang me-rantai User::factory() bare (seluruh
            // test Fase 1-3 memakai helper `makeTestUser()` yang mengisi ini
            // eksplisit). `CashierShiftFactory` (T4.1) adalah rantai pertama
            // (Sale -> CashierShift -> User) yang membuat User lewat factory
            // murni, menyingkap gap ini.
            'default_branch_id' => Branch::factory(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
