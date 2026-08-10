<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Seeder Fase 2 (T2.7): contoh jasa untuk pengembangan dan demo —
     * bukan data produksi (R9, clean start).
     */
    public function run(): void
    {
        Service::firstOrCreate(
            ['code' => 'SVC-KONS'],
            [
                'name' => 'Konsultasi Kebutuhan Komputer',
                'category' => 'Konsultasi',
                'price' => 50_000,
                'is_active' => true,
            ]
        );

        Service::firstOrCreate(
            ['code' => 'SVC-RAKIT'],
            [
                'name' => 'Perakitan PC',
                'category' => 'Perakitan',
                'price' => 150_000,
                'is_active' => true,
            ]
        );

        Service::firstOrCreate(
            ['code' => 'SVC-KALIB'],
            [
                'name' => 'Kalibrasi Monitor',
                'category' => 'Kalibrasi',
                'price' => 75_000,
                'is_active' => true,
            ]
        );
    }
}
