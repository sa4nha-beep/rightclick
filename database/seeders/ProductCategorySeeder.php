<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    /**
     * Seeder Fase 2 (T2.3): kategori produk dasar untuk pengembangan dan
     * demo — bukan data produksi (R9, clean start).
     */
    public function run(): void
    {
        ProductCategory::firstOrCreate(
            ['code' => 'KOMP'],
            ['name' => 'Komponen', 'is_active' => true]
        );

        ProductCategory::firstOrCreate(
            ['code' => 'MON'],
            ['name' => 'Monitor', 'is_active' => true]
        );

        ProductCategory::firstOrCreate(
            ['code' => 'KBD'],
            ['name' => 'Keyboard', 'is_active' => true]
        );

        ProductCategory::firstOrCreate(
            ['code' => 'MSE'],
            ['name' => 'Mouse', 'is_active' => true]
        );

        ProductCategory::firstOrCreate(
            ['code' => 'PRN'],
            ['name' => 'Printer', 'is_active' => true]
        );
    }
}
