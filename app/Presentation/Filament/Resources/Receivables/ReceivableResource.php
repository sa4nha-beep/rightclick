<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Receivables;

use App\Infrastructure\Persistence\Models\Receivable;
use App\Presentation\Filament\Resources\Receivables\Pages\ListReceivables;
use App\Presentation\Filament\Resources\Receivables\Tables\ReceivablesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Penutup gap T5.6 asli (`HS-TASKS-RIGHTCLICK-v1.1`: "Daftar piutang dan
 * hutang dengan umur dan jatuh tempo") — sebelumnya tidak ada tabel
 * `receivables` sungguhan untuk ditampilkan (T5.5 self-derived hanya
 * menghitung saldo on-the-fly). List-only, pola sama `StockBalanceResource`
 * — cache turunan, tidak ada create/edit lewat panel.
 */
class ReceivableResource extends Resource
{
    protected static ?string $model = Receivable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Piutang';

    protected static ?string $modelLabel = 'Piutang';

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    public static function table(Table $table): Table
    {
        return ReceivablesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceivables::route('/'),
        ];
    }
}
