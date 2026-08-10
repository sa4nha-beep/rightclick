<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashierShifts;

use App\Infrastructure\Persistence\Models\CashierShift;
use App\Presentation\Filament\Resources\CashierShifts\Pages\CreateCashierShift;
use App\Presentation\Filament\Resources\CashierShifts\Pages\EditCashierShift;
use App\Presentation\Filament\Resources\CashierShifts\Pages\ListCashierShifts;
use App\Presentation\Filament\Resources\CashierShifts\Schemas\CashierShiftForm;
use App\Presentation\Filament\Resources\CashierShifts\Tables\CashierShiftsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T4.1 — shift kasir. Tutup/void adalah Action khusus (`CashierShiftsTable`),
 * bukan `EditRecord::save()` — sama pola dengan `StockAdjustmentResource`.
 */
class CashierShiftResource extends Resource
{
    protected static ?string $model = CashierShift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Shift Kasir';

    protected static ?string $modelLabel = 'Shift Kasir';

    protected static string|\UnitEnum|null $navigationGroup = 'Penjualan';

    public static function form(Schema $schema): Schema
    {
        return CashierShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashierShiftsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashierShifts::route('/'),
            'create' => CreateCashierShift::route('/create'),
            'edit' => EditCashierShift::route('/{record}/edit'),
        ];
    }
}
