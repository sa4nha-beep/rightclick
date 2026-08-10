<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashierShifts\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Membuka shift (T4.1) — hanya `opening_cash`. `cashier_id` diisi otomatis
 * dari pengguna login (shift selalu milik diri sendiri lewat form ini;
 * Admin yang membuka atas nama Kasir lain adalah kasus tepi di luar cakupan
 * T4.1). Field penutupan (`closing_cash_counted` dst.) TIDAK ada di form —
 * diisi lewat Action `close` di `CashierShiftsTable`, bukan edit form biasa.
 */
class CashierShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('cashier_id')
                    ->default(fn () => Auth::id())
                    ->dehydrated(),
                TextInput::make('opening_cash')
                    ->label('Kas Awal')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->minValue(0),
            ]);
    }
}
