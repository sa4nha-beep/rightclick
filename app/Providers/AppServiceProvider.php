<?php

namespace App\Providers;

use App\Http\Middleware\SetActiveBranchContext;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Infrastructure\Persistence\Support\MigrationMacros;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: Laravel mengosongkan instance ini di antara permintaan HTTP
        // dan di antara job queue, sehingga worker yang sama tidak membawa
        // cabang dari job sebelumnya (App\Infrastructure\Persistence\Support\BranchContext).
        $this->app->scoped(BranchContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MigrationMacros::register();

        // Bug nyata ditemukan lewat log produksi (bukan test — Livewire::test()
        // diam-diam melewati SELURUH middleware, gotcha yang sudah didokumentasikan
        // berulang di CLAUDE.md T1.13/T5.6): `POST /livewire/update` — endpoint
        // TUNGGAL yang menangani SETIAP interaksi Livewire setelah page load awal
        // (termasuk tombol "Create"/"Save" pada SEMUA Resource Filament DAN
        // checkout POS) — didaftarkan Livewire SENDIRI hanya dengan middleware
        // `web`, SAMA SEKALI DI LUAR route group panel Filament
        // (`AdminPanelProvider::authMiddleware()`) maupun group `/pos`
        // (routes/web.php). Akibatnya `SetActiveBranchContext` TIDAK PERNAH
        // berjalan pada request yang benar-benar menyimpan data — `BranchContext`
        // kosong persis saat `BelongsToBranch::creating()` membutuhkannya,
        // menyebabkan SETIAP dokumen branch-scoped (Sale, StockAdjustment,
        // StockOpname, StockTransfer, CashierShift, GoodsReceipt, PurchaseOrder,
        // PurchaseInvoice) gagal dibuat dengan pelanggaran NOT NULL `branch_id`.
        // Diperbaiki dengan mendaftarkan ulang route Livewire memakai mekanisme
        // resmi `Livewire::setUpdateRoute()`, menambahkan middleware yang sama
        // yang sudah dipakai kedua permukaan (panel Filament DAN /pos). TIDAK
        // menambahkan `auth` di sini — endpoint ini juga melayani form login
        // Filament sendiri (Livewire), yang WAJIB bisa diakses tanpa autentikasi;
        // `SetActiveBranchContext` sendiri sudah aman dipanggil tanpa pengguna
        // login (no-op bila `Auth::user()` null, lihat isi middleware-nya).
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)
            ->middleware(['web', SetActiveBranchContext::class]));
    }
}
