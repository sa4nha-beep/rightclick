<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashEntries;

use App\Infrastructure\Persistence\Models\CashEntry;
use App\Presentation\Filament\Resources\CashEntries\Pages\ListCashEntries;
use App\Presentation\Filament\Resources\CashEntries\Pages\ViewCashEntry;
use App\Presentation\Filament\Resources\CashEntries\Schemas\CashEntryInfolist;
use App\Presentation\Filament\Resources\CashEntries\Tables\CashEntriesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T5.6 — ledger kas (`CashEntryPolicy`, T5.4, AC-21). TIDAK ADA halaman
 * create/edit — append-only, satu-satunya jalur tulis adalah
 * `CashLedgerService` (sama seperti `StockMutationResource`, T3.3).
 *
 * TANPA `getEloquentQuery()` penyaring kolom — `CashEntryPolicy` tidak
 * punya gate kolom sensitif seperti `view_stock_cost` pada
 * `StockMutationResource`.
 */
class CashEntryResource extends Resource
{
    protected static ?string $model = CashEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Ledger Kas';

    protected static ?string $modelLabel = 'Entri Kas';

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    public static function infolist(Schema $schema): Schema
    {
        return CashEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashEntries::route('/'),
            'view' => ViewCashEntry::route('/{record}'),
        ];
    }
}
