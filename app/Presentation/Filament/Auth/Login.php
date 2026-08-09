<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Auth;

use App\Application\Services\NodeConnectivityService;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use SensitiveParameter;

/**
 * Halaman login kustom (T1.11, HS-UI-RIGHTCLICK-v1.1 §8.2/§8.3).
 *
 * Diletakkan di luar `Presentation/Filament/Pages` (direktori yang
 * di-`discoverPages()` AdminPanelProvider) supaya tidak ikut terdaftar
 * sebagai item navigasi — halaman ini didaftarkan eksplisit lewat
 * `->login(Login::class)`, bukan auto-discovery.
 *
 * Dua penyesuaian dari bawaan Filament:
 *
 * 1. Kredensial login memakai `username` (kolom UNIK wajib di tabel `users`,
 *    T1.4), BUKAN `email` (nullable, bukan pengenal login). Bawaan Filament
 *    `Filament\Auth\Pages\Login` memakai field `email` — dibiarkan begitu
 *    saja, tidak ada pengguna yang bisa login sama sekali bila `email`
 *    kosong (kasus umum: kasir/gudang biasanya tidak diberi alamat surel).
 * 2. Banner "Terputus dari pusat" (U8) tampil di atas form bila
 *    `NodeConnectivityService` mendeteksi node cabang tidak tersambung ke
 *    HQ. Pesan WAJIB menegaskan transaksi tetap tersimpan (U8) — kegagalan
 *    login BUKAN indikasi data hilang, sekadar peringatan konteks sebelum
 *    pengguna masuk.
 *
 * Keadaan "kredensial salah" tidak memerlukan kode tambahan di sini — sudah
 * ditangani penuh oleh `authenticate()` bawaan (`Login::class` induk),
 * termasuk rate limiting dan pesan galat standar.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Nama Pengguna')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getConnectivityBannerComponent(),
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE),
                $this->getFormContentComponent(),
                $this->getMultiFactorChallengeFormContentComponent(),
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
            ]);
    }

    protected function getConnectivityBannerComponent(): Component
    {
        return Callout::make('Terputus dari pusat')
            ->description('Transaksi tetap tersimpan secara lokal dan akan tersinkron otomatis begitu koneksi pulih.')
            ->warning()
            ->visible(fn (): bool => ! app(NodeConnectivityService::class)->isConnectedToHq());
    }
}
