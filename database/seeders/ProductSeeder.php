<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Infrastructure\Persistence\Models\Unit;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seeder Fase 2 (T2.5): contoh produk lintas kategori untuk
     * pengembangan dan demo — bukan data produksi (R9, clean start).
     * Merujuk kategori (T2.3) dan satuan (T2.4) yang sudah diseed lebih
     * dulu di `DatabaseSeeder`.
     */
    public function run(): void
    {
        $categories = ProductCategory::query()->pluck('id', 'code');
        $pcs = Unit::query()->where('code', 'PCS')->value('id');

        $products = [
            ['sku' => 'KOMP-VGA-001', 'name' => 'VGA Card RTX 4060 8GB', 'category' => 'KOMP', 'price' => 5_500_000],
            ['sku' => 'KOMP-MBO-001', 'name' => 'Motherboard B760M DDR5', 'category' => 'KOMP', 'price' => 2_100_000],
            ['sku' => 'KOMP-RAM-001', 'name' => 'RAM DDR5 16GB 5600MHz', 'category' => 'KOMP', 'price' => 950_000],
            ['sku' => 'KOMP-SSD-001', 'name' => 'SSD NVMe 1TB Gen4', 'category' => 'KOMP', 'price' => 1_150_000],
            ['sku' => 'MON-001', 'name' => 'Monitor LED 24" IPS 100Hz', 'category' => 'MON', 'price' => 1_650_000],
            ['sku' => 'MON-002', 'name' => 'Monitor LED 27" IPS 144Hz', 'category' => 'MON', 'price' => 2_800_000],
            ['sku' => 'KBD-001', 'name' => 'Keyboard Mechanical RGB', 'category' => 'KBD', 'price' => 450_000],
            ['sku' => 'MSE-001', 'name' => 'Mouse Wireless Gaming', 'category' => 'MSE', 'price' => 280_000],
            ['sku' => 'MSE-002', 'name' => 'Mouse Optical Standar', 'category' => 'MSE', 'price' => 65_000],
            ['sku' => 'PRN-001', 'name' => 'Printer Inkjet All-in-One', 'category' => 'PRN', 'price' => 1_250_000],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['sku' => $product['sku']],
                [
                    'name' => $product['name'],
                    'product_category_id' => $categories[$product['category']],
                    'base_unit_id' => $pcs,
                    'selling_price' => $product['price'],
                    'is_active' => true,
                    'is_serialized' => false,
                ]
            );
        }
    }
}
