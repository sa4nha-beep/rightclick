<?php

declare(strict_types=1);

/**
 * T1.1 — acceptance criteria: halaman Filament dapat diakses.
 *
 * Tampilan panel (tema merek, Inter lokal, sidebar hitam) adalah T1.10;
 * di sini yang diuji hanya bahwa panel terpasang dan terjangkau.
 */
it('menyajikan halaman login panel admin', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('RIGHTCLICK', escape: false);
});

it('mengalihkan panel admin ke login saat belum terautentikasi', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
