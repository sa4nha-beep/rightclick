<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Seeder Fase 2 (T2.4): satuan pengukuran dasar untuk pengembangan dan
     * demo — bukan data produksi (R9, clean start).
     */
    public function run(): void
    {
        Unit::firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces', 'is_active' => true]);
        Unit::firstOrCreate(['code' => 'BOX'], ['name' => 'Box', 'is_active' => true]);
        Unit::firstOrCreate(['code' => 'RIM'], ['name' => 'Rim', 'is_active' => true]);
        Unit::firstOrCreate(['code' => 'DRUM'], ['name' => 'Drum', 'is_active' => true]);
        Unit::firstOrCreate(['code' => 'MTR'], ['name' => 'Meter', 'is_active' => true]);
    }
}
