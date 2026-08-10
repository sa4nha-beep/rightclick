<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockOpnames;

use App\Infrastructure\Persistence\Models\StockOpname;
use App\Presentation\Filament\Resources\StockOpnames\Pages\CreateStockOpname;
use App\Presentation\Filament\Resources\StockOpnames\Pages\EditStockOpname;
use App\Presentation\Filament\Resources\StockOpnames\Pages\ListStockOpnames;
use App\Presentation\Filament\Resources\StockOpnames\Schemas\StockOpnameForm;
use App\Presentation\Filament\Resources\StockOpnames\Tables\StockOpnamesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T3.4 — stock opname. Draft dapat diedit bebas lewat form biasa; finalisasi
 * dan void adalah Action khusus (`StockOpnamesTable`) yang memanggil
 * `FinalizeStockOpnameAction`/`VoidStockOpnameAction` — BUKAN
 * `EditRecord::save()` biasa, karena keduanya punya efek samping ledger
 * (R1) di luar sekadar mengubah kolom.
 */
class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Stock Opname';

    protected static ?string $modelLabel = 'Stock Opname';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventaris';

    public static function form(Schema $schema): Schema
    {
        return StockOpnameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockOpnamesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockOpnames::route('/'),
            'create' => CreateStockOpname::route('/create'),
            'edit' => EditStockOpname::route('/{record}/edit'),
        ];
    }
}
