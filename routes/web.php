<?php

use App\Http\Middleware\SetActiveBranchContext;
use App\Presentation\Pos\Http\Controllers\ShowSaleReceiptController;
use App\Presentation\Pos\Livewire\PosTerminal;
use Illuminate\Support\Facades\Route;

// RIGHTCLICK adalah alat internal tanpa halaman publik (§1) — dua pintu
// masuk nyata adalah back office (/admin) dan POS (/pos). View `welcome`
// bawaan Laravel (font CDN, dark mode, branding Laravel/Laracasts) sengaja
// dihapus (T1.1 leftover yang tidak pernah dibersihkan) — melanggar R13/UT15
// (font lokal) dan "dark mode dinonaktifkan" (§9) begitu ada yang membukanya.
Route::get('/', fn () => redirect('/admin'));

/*
 * POS (T4.4) — di luar panel Filament (`App\Presentation\Pos`,
 * HS-ARCH-RIGHTCLICK-v1.1 §2.2). Sama guard sesi ('web') dengan login
 * Filament (`App\Presentation\Filament\Auth\Login`, T1.11) — kasir yang
 * sudah login lewat `/admin/login` otomatis terautentikasi di sini juga,
 * tidak ada sistem login terpisah. `SetActiveBranchContext` WAJIB —
 * `BelongsToBranch` (Sale/CashierShift) membaca `BranchContext` saat
 * `creating`, sama persis kebutuhannya dengan resource Filament (T2.8).
 */
Route::middleware(['auth', SetActiveBranchContext::class])->group(function () {
    Route::get('/pos', PosTerminal::class)->name('pos.terminal');
    Route::get('/pos/sales/{sale}/receipt', ShowSaleReceiptController::class)->name('pos.receipt');
});
