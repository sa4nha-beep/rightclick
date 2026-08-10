<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Seeder Fase 2 (T2.2): contoh pemasok dan pelanggan untuk pengembangan
     * dan demo — bukan data produksi (R9, clean start).
     */
    public function run(): void
    {
        Partner::firstOrCreate(
            ['code' => 'SUP-001'],
            [
                'name' => 'PT Sumber Komputer Jaya',
                'partner_type' => 'supplier',
                'tax_id' => '01.234.567.8-901.000',
                'phone' => '0291-123456',
                'email' => 'sales@sumberkomputer.example',
                'address' => 'Jl. Industri Raya No. 12',
                'city' => 'Semarang',
                'contact_person' => 'Budi Santoso',
                'payment_terms_days' => 30,
                'is_active' => true,
            ]
        );

        Partner::firstOrCreate(
            ['code' => 'SUP-002'],
            [
                'name' => 'CV Mitra Elektronik Nusantara',
                'partner_type' => 'supplier',
                'tax_id' => '02.345.678.9-012.000',
                'phone' => '024-7654321',
                'email' => 'purchasing@mitraelektronik.example',
                'address' => 'Jl. Gatot Subroto No. 45',
                'city' => 'Semarang',
                'contact_person' => 'Siti Rahayu',
                'payment_terms_days' => 14,
                'is_active' => true,
            ]
        );

        Partner::firstOrCreate(
            ['code' => 'CUST-001'],
            [
                'name' => 'CV Berkah Digital Solusi',
                'partner_type' => 'customer',
                'tax_id' => null,
                'phone' => '0291-998877',
                'email' => 'admin@berkahdigital.example',
                'address' => 'Jl. Sunan Kudus No. 8',
                'city' => 'Kudus',
                'contact_person' => 'Ahmad Fauzi',
                'credit_limit' => 10_000_000,
                'payment_terms_days' => 7,
                'is_active' => true,
            ]
        );
    }
}
