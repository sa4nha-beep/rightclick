<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockTransfers;

use App\Infrastructure\Persistence\Models\StockTransfer;
use App\Presentation\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Presentation\Filament\Resources\StockTransfers\Pages\EditStockTransfer;
use App\Presentation\Filament\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Presentation\Filament\Resources\StockTransfers\Schemas\StockTransferForm;
use App\Presentation\Filament\Resources\StockTransfers\Tables\StockTransfersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T3.6 — dokumen KIRIM transfer antar cabang (R12). Kirim/Terima/Batalkan
 * adalah Action khusus (`StockTransfersTable`), bukan `EditRecord::save()`
 * — sama pola dengan opname/adjustment (T3.4/T3.5).
 */
class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Transfer Stok';

    protected static ?string $modelLabel = 'Transfer Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventaris';

    public static function form(Schema $schema): Schema
    {
        return StockTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockTransfersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockTransfers::route('/'),
            'create' => CreateStockTransfer::route('/create'),
            'edit' => EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
