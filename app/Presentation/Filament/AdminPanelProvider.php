<?php

declare(strict_types=1);

namespace App\Presentation\Filament;

use App\Presentation\Filament\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel back office (Filament).
 *
 * POS sengaja TIDAK dibangun di sini — POS adalah halaman Livewire mandiri di
 * `App\Presentation\Pos` (HS-ARCH-RIGHTCLICK-v1.1 bagian 2.2, trade-off A5).
 *
 * Tema, warna merek, font Inter lokal, dan sidebar hitam adalah lingkup T1.10;
 * di T1.1 panel sengaja dibiarkan pada tampilan bawaan agar tidak mendahului
 * `HS-UI-RIGHTCLICK-v1.1`.
 *
 * Logo (T1.10, HS-UI-RIGHTCLICK-v1.1 §5): Filament memakai SATU
 * `<x-filament-panels::logo>` yang sama di sidebar (latar hitam) maupun
 * header halaman "simple" (login, latar putih) — tidak ada API bawaan untuk
 * membedakan varian per permukaan. `brandLogoHtml()` merender KEDUA varian
 * sekaligus (Primary Horizontal + Reverse) sebagai HTML mentah; CSS di
 * `resources/css/filament-theme.css` yang memilih mana yang tampil,
 * diseleksi lewat kelas leluhur Filament yang stabil (`.fi-simple-header`,
 * `.fi-topbar` = latar putih; `.fi-sidebar-header-logo-ctn` = latar hitam).
 * Pendekatan ini TIDAK mengubah warna file SVG sama sekali (LG2) — hanya
 * memilih file mana yang ditampilkan.
 *
 * Belum diimplementasikan (gap terdokumentasi, bukan terlewat tanpa
 * sengaja): NV3 — logo berubah ke Wordmark Only saat sidebar diciutkan ke
 * 64px. Filament menyembunyikan seluruh `.fi-sidebar-header-logo-ctn` lewat
 * `x-show` Alpine saat sidebar tertutup, jadi swap CSS di dalam elemen yang
 * sama tidak akan pernah terlihat — perlu override view `sidebar.blade.php`
 * milik Filament, bukan sekadar konfigurasi `->brandLogo()`. Sengaja ditunda
 * karena `->sidebarCollapsibleOnDesktop()` sendiri belum diaktifkan (tidak
 * ada AC T1.10 yang menuntutnya), dan mengaktifkan mode ciut tanpa logo
 * fallback yang benar akan meninggalkan header kosong saat diciutkan.
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Dua varian logo (Primary Horizontal untuk latar putih, Reverse untuk
     * latar hitam) dirender bersamaan; visibilitas per varian diatur murni
     * lewat CSS berdasarkan konteks leluhur (lihat komentar kelas di atas).
     */
    private static function brandLogoHtml(): Htmlable
    {
        $primary = asset('images/brand/HK-LOGO-01-Primary-Horizontal.svg');
        $reverse = asset('images/brand/HK-LOGO-05-Reverse.svg');

        return new HtmlString(
            '<img src="'.e($primary).'" alt="HAEN KOMPUTER" class="rc-brand-logo rc-brand-logo-primary" />'
            .'<img src="'.e($reverse).'" alt="HAEN KOMPUTER" class="rc-brand-logo rc-brand-logo-reverse" />'
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Kustom (T1.11): login memakai `username`, bukan `email` (lihat
            // dokblok App\Presentation\Filament\Auth\Login).
            ->login(Login::class)
            // Dark mode dinonaktifkan — Brand Identity menetapkan proporsi
            // 60% putih / 30% hitam / 10% cyan (T1.10, HS-UI bagian 2.1).
            ->darkMode(false)
            // Brand HAEN KOMPUTER: warna cyan (#00B4D4) sebagai primary
            ->colors([
                'primary' => Color::hex('#00B4D4'),
                'danger' => Color::hex('#DC2626'),
                'warning' => Color::hex('#D97706'),
                'success' => Color::hex('#0E9F6E'),
                'info' => Color::hex('#00B4D4'),
            ])
            ->brandName('HAEN KOMPUTER')
            ->favicon(asset('images/brand/favicon/favicon.svg'))
            ->brandLogo(fn (): Htmlable => self::brandLogoHtml())
            // Entry Vite terpisah dari resources/css/app.css (default Laravel,
            // tidak dipakai panel Filament). Tanpa ini, filament-theme.css
            // TIDAK PERNAH dimuat browser — panel diam-diam jatuh ke tema
            // bawaan Filament meski kode overridenya ada (ditemukan T1.10).
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(
                in: app_path('Presentation/Filament/Resources'),
                for: 'App\Presentation\Filament\Resources',
            )
            ->discoverPages(
                in: app_path('Presentation/Filament/Pages'),
                for: 'App\Presentation\Filament\Pages',
            )
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Presentation/Filament/Widgets'),
                for: 'App\Presentation\Filament\Widgets',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                // PT10 (HS-PERM-RIGHTCLICK-v1.2 §7 — akun nonaktif ditolak
                // pada permintaan berikutnya, sesi diakhiri) ditegakkan di
                // dalam `User::canAccessPanel()`, dipanggil `Authenticate`
                // di bawah — BUKAN lewat middleware kustom terpisah yang
                // ditaruh sebelum kelas ini. Percobaan menaruh pemeriksaan
                // di middleware sendiri sebelum `Authenticate::class`
                // terbukti tidak konsisten terpanggil pada jalur permintaan
                // Livewire full-page Filament; `canAccessPanel()` terbukti
                // SELALU dipanggil tepat sebelum keputusan izin, sehingga
                // itulah titik yang dipakai untuk logout + invalidate sesi.
                Authenticate::class,
            ]);
    }
}
