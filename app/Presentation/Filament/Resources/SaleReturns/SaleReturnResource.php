<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\SaleReturns;

use App\Infrastructure\Persistence\Models\SaleReturn;
use App\Presentation\Filament\Resources\SaleReturns\Pages\CreateSaleReturn;
use App\Presentation\Filament\Resources\SaleReturns\Pages\EditSaleReturn;
use App\Presentation\Filament\Resources\SaleReturns\Pages\ListSaleReturns;
use App\Presentation\Filament\Resources\SaleReturns\Schemas\SaleReturnForm;
use App\Presentation\Filament\Resources\SaleReturns\Tables\SaleReturnsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T4.3 — retur penjualan (AC-18). Finalisasi/void adalah Action khusus
 * (`SaleReturnsTable`), bukan `EditRecord::save()` — sama pola dengan
 * `SaleResource`.
 */
class SaleReturnResource extends Resource
{
    protected static ?string $model = SaleReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $navigationLabel = 'Retur Penjualan';

    protected static ?string $modelLabel = 'Retur Penjualan';

    protected static string|\UnitEnum|null $navigationGroup = 'Penjualan';

    public static function form(Schema $schema): Schema
    {
        return SaleReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SaleReturnsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSaleReturns::route('/'),
            'create' => CreateSaleReturn::route('/create'),
            'edit' => EditSaleReturn::route('/{record}/edit'),
        ];
    }
}
