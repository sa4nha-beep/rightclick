<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\DB;

/**
 * T1.9 — seeder ambang TH1–TH5c (HS-PRD-RIGHTCLICK-v1.0 §5.1). Nilai dan
 * kunci di sini WAJIB sama persis dengan tabel di dokumen tersebut — modul
 * yang membaca ambang (T2.7, T3.6, T4.8, T5.1) bergantung pada kunci ini.
 */
beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('menyeed seluruh delapan ambang TH1-TH5c dengan nilai yang benar', function () {
    (new SettingSeeder)->run();

    expect(Setting::get('discount.max_kasir'))->toBe(100000)
        ->and(Setting::get('discount.max_admin'))->toBe(300000)
        ->and(Setting::get('adjustment.max_admin'))->toBe(5000000)
        ->and(Setting::get('adjustment.max_admin_monthly'))->toBe(15000000)
        ->and(Setting::get('po.max_admin'))->toBe(10000000)
        ->and(Setting::get('price.threshold_increase'))->toBe(0.10)
        ->and(Setting::get('price.threshold_decrease'))->toBe(0.05)
        ->and(Setting::get('price.block_below_cost'))->toBeTrue();
});

it('bersifat idempoten — dijalankan dua kali tidak menduplikasi baris', function () {
    (new SettingSeeder)->run();
    (new SettingSeeder)->run();

    expect(Setting::count())->toBe(8);
});

it('mengembalikan default bila kunci belum diseed', function () {
    expect(Setting::get('kunci.tidak.ada', 'fallback'))->toBe('fallback')
        ->and(Setting::get('kunci.tidak.ada'))->toBeNull();
});
