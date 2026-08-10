<?php

use App\Http\Middleware\SetActiveBranchContext;
use App\Presentation\Pos\Http\Controllers\ShowSaleReceiptController;
use App\Presentation\Pos\Livewire\PosTerminal;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
