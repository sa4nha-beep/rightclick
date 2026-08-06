<?php

declare(strict_types=1);

namespace App\Presentation\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
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
                Authenticate::class,
            ]);
    }
}
