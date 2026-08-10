<?php

declare(strict_types=1);

namespace Database\Factories\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\PartnerType;
use App\Infrastructure\Persistence\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'code' => 'PTR-'.strtoupper(fake()->unique()->bothify('????##')),
            'name' => fake()->company(),
            'partner_type' => fake()->randomElement(PartnerType::cases())->value,
            'tax_id' => null,
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'contact_person' => fake()->name(),
            'credit_limit' => null,
            'payment_terms_days' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
