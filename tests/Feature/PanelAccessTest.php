<?php

declare(strict_types=1);

/**
 * T1.1 — acceptance criteria: halaman Filament dapat diakses.
 *
 * Tampilan panel (tema merek, Inter lokal, sidebar hitam) adalah T1.10;
 * di sini yang diuji hanya bahwa panel terpasang dan terjangkau.
 *
 * Teks "HAEN KOMPUTER" (bukan "RIGHTCLICK") sengaja diuji: RIGHTCLICK adalah
 * nama proyek internal (CLAUDE.md §1), sedangkan HAEN KOMPUTER adalah brand
 * yang tampil ke pengguna akhir — dikonfirmasi lewat `->brandName()` di
 * AdminPanelProvider (T1.10).
 */
it('menyajikan halaman login panel admin', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('HAEN KOMPUTER', escape: false);
});

it('mengalihkan panel admin ke login saat belum terautentikasi', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
