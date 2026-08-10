<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseOrders;

use App\Infrastructure\Persistence\Models\PurchaseOrder;
use App\Presentation\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Presentation\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Presentation\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Presentation\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Presentation\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T5.6 — Purchase order (T5.1). Finalisasi/approve/void adalah Action
 * khusus (`PurchaseOrdersTable`), bukan `EditRecord::save()` — sama pola
 * dengan `StockAdjustmentResource` (TH4 punya alur approval sama seperti
 * TH3).
 */
class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Purchase Order';

    protected static ?string $modelLabel = 'Purchase Order';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
