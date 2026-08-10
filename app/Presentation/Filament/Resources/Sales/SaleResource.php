<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Sales;

use App\Infrastructure\Persistence\Models\Sale;
use App\Presentation\Filament\Resources\Sales\Pages\CreateSale;
use App\Presentation\Filament\Resources\Sales\Pages\EditSale;
use App\Presentation\Filament\Resources\Sales\Pages\ListSales;
use App\Presentation\Filament\Resources\Sales\Schemas\SaleForm;
use App\Presentation\Filament\Resources\Sales\Tables\SalesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T4.1 — penjualan retail. Back-office fallback: POS Livewire mandiri
 * (T4.4, `App\Presentation\Pos\Livewire\PosTerminal`, route `/pos`) adalah
 * jalur SUNGGUHAN dipakai Kasir sehari-hari — resource ini tetap perlu
 * untuk review Admin/Owner (`view_sales`) dan sebagai jalur uji end-to-end.
 * Finalisasi/persetujuan diskon (T4.2)/void adalah Action khusus
 * (`SalesTable`: `finalize`/`approve`/`void`), bukan `EditRecord::save()` —
 * sama pola dengan `StockAdjustmentResource`.
 */
class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Penjualan';

    protected static ?string $modelLabel = 'Penjualan';

    protected static string|\UnitEnum|null $navigationGroup = 'Penjualan';

    public static function form(Schema $schema): Schema
    {
        return SaleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'create' => CreateSale::route('/create'),
            'edit' => EditSale::route('/{record}/edit'),
        ];
    }
}
