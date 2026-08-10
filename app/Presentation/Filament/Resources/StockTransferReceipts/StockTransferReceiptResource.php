<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockTransferReceipts;

use App\Infrastructure\Persistence\Models\StockTransferReceipt;
use App\Presentation\Filament\Resources\StockTransferReceipts\Pages\ListStockTransferReceipts;
use App\Presentation\Filament\Resources\StockTransferReceipts\Tables\StockTransferReceiptsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T3.6 — dokumen TERIMA transfer. Read-only di panel — satu-satunya jalur
 * tulis adalah `ReceiveStockTransferAction`, dipicu dari
 * `StockTransferResource` (aksi "Terima" pada dokumen kirim).
 */
class StockTransferReceiptResource extends Resource
{
    protected static ?string $model = StockTransferReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Penerimaan Transfer';

    protected static ?string $modelLabel = 'Penerimaan Transfer';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventaris';

    public static function table(Table $table): Table
    {
        return StockTransferReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockTransferReceipts::route('/'),
        ];
    }
}
