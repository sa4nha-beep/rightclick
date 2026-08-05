<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas Node
    |--------------------------------------------------------------------------
    |
    | Satu basis kode dipakai oleh tiga node: Cabang A, Cabang B, dan HQ.
    | Pembedanya hanya nilai di bawah ini, disetel per node melalui .env.
    |
    | Referensi: HS-ARCH-RIGHTCLICK-v1.1 bagian 1.1.
    |
    */

    'node' => [

        // NodeRole::Hq atau NodeRole::Branch
        'role' => env('RIGHTCLICK_NODE_ROLE', NodeRole::Branch->value),

        // Kode cabang yang dipakai pada penomoran dokumen (R6) dan payload
        // sinkronisasi. Wajib unik antar node. Contoh: HK-A, HK-B, HK-HQ.
        'branch_code' => env('RIGHTCLICK_BRANCH_CODE', 'HK-A'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Zona Waktu Tampilan
    |--------------------------------------------------------------------------
    |
    | DB Design 7: seluruh `timestamptz` DISIMPAN dalam UTC (APP_TIMEZONE=UTC)
    | dan hanya DITAMPILKAN sebagai Asia/Jakarta. Nilai di bawah dipakai oleh
    | lapisan Presentation; jangan mengubah APP_TIMEZONE untuk tujuan tampilan.
    |
    */

    'display_timezone' => env('RIGHTCLICK_DISPLAY_TIMEZONE', 'Asia/Jakarta'),

];
