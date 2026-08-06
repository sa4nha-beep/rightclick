<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seeder T1.9 — nilai ambang TH1–TH5c ditetapkan COO
     * (HS-PRD-RIGHTCLICK-v1.0 §5.1). Kunci persis seperti kolom "Setting
     * Key" pada dokumen tersebut, agar modul yang membaca ambang (T2.7,
     * T3.6, T4.8, T5.1) memakai nama yang sama dengan yang ditinjau COO.
     */
    public function run(): void
    {
        $thresholds = [
            [
                'key' => 'discount.max_kasir',
                'value' => 100000,
                'description' => 'TH1 — Batas diskon maksimum Kasir per transaksi (Rp). Melebihi ini menunggu approval, transaksi tidak ditolak (AP-01).',
            ],
            [
                'key' => 'discount.max_admin',
                'value' => 300000,
                'description' => 'TH2 — Batas diskon maksimum Admin per transaksi (Rp).',
            ],
            [
                'key' => 'adjustment.max_admin',
                'value' => 5000000,
                'description' => 'TH3 — Batas nilai absolut (qty × unit_cost) penyesuaian stok per dokumen tanpa approval Owner (Rp).',
            ],
            [
                'key' => 'adjustment.max_admin_monthly',
                'value' => 15000000,
                'description' => 'TH3b — Batas kumulatif nilai absolut penyesuaian stok per Admin per bulan kalender (Rp). Melampauinya memerlukan approval Owner untuk sisa bulan berjalan.',
            ],
            [
                'key' => 'po.max_admin',
                'value' => 10000000,
                'description' => 'TH4 — Batas nilai total purchase order tanpa approval Owner (Rp).',
            ],
            [
                'key' => 'price.threshold_increase',
                'value' => 0.10,
                'description' => 'TH5a — Ambang kenaikan harga jual yang memicu approval (fraksi, mis. 0.10 = 10%).',
            ],
            [
                'key' => 'price.threshold_decrease',
                'value' => 0.05,
                'description' => 'TH5b — Ambang penurunan harga jual yang memicu approval (fraksi, mis. 0.05 = 5%). Sengaja lebih ketat dari TH5a — penurunan merusak margin.',
            ],
            [
                'key' => 'price.block_below_cost',
                'value' => true,
                'description' => 'TH5c — Harga jual di bawah unit_cost batch tertua yang masih tersisa selalu memicu approval, mengabaikan TH5a/TH5b berapa pun persentasenya.',
            ],
        ];

        foreach ($thresholds as $threshold) {
            Setting::updateOrCreate(
                ['key' => $threshold['key']],
                ['value' => $threshold['value'], 'description' => $threshold['description']],
            );
        }
    }
}
