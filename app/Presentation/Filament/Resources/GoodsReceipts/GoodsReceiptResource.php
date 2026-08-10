<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\GoodsReceipts;

use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Presentation\Filament\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use App\Presentation\Filament\Resources\GoodsReceipts\Pages\EditGoodsReceipt;
use App\Presentation\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Presentation\Filament\Resources\GoodsReceipts\Schemas\GoodsReceiptForm;
use App\Presentation\Filament\Resources\GoodsReceipts\Tables\GoodsReceiptsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T5.6 — Penerimaan barang (T5.2, simpul kritis). Finalisasi/void adalah
 * Action khusus (`GoodsReceiptsTable`) — TANPA alur approval (§10 tidak
 * menetapkan TH untuk goods receipt, beda dari `PurchaseOrderResource`).
 */
class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Penerimaan Barang';

    protected static ?string $modelLabel = 'Penerimaan Barang';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    public static function form(Schema $schema): Schema
    {
        return GoodsReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GoodsReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoodsReceipts::route('/'),
            'create' => CreateGoodsReceipt::route('/create'),
            'edit' => EditGoodsReceipt::route('/{record}/edit'),
        ];
    }
}
