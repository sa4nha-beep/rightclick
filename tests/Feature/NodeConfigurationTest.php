<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;

/**
 * Satu basis kode melayani tiga node. Bila `rightclick.node.role` berisi nilai
 * di luar enum, node akan berjalan tanpa peran yang jelas — dan pelanggaran
 * "hanya HQ yang menulis master data" baru ketahuan di produksi.
 */
it('menyetel peran node ke nilai yang dikenal', function () {
    expect(NodeRole::tryFrom(config('rightclick.node.role')))
        ->toBeInstanceOf(NodeRole::class);
});

it('menyetel kode cabang yang dipakai penomoran dokumen', function () {
    expect(config('rightclick.node.branch_code'))->not->toBeEmpty();
});

it('menyimpan waktu dalam UTC dan menampilkan Asia/Jakarta', function () {
    // DB Design 7 — seluruh timestamptz disimpan UTC.
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('rightclick.display_timezone'))->toBe('Asia/Jakarta');
});
