<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockAdjustments;

use App\Infrastructure\Persistence\Models\StockAdjustment;
use App\Presentation\Filament\Resources\StockAdjustments\Pages\CreateStockAdjustment;
use App\Presentation\Filament\Resources\StockAdjustments\Pages\EditStockAdjustment;
use App\Presentation\Filament\Resources\StockAdjustments\Pages\ListStockAdjustments;
use App\Presentation\Filament\Resources\StockAdjustments\Schemas\StockAdjustmentForm;
use App\Presentation\Filament\Resources\StockAdjustments\Tables\StockAdjustmentsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T3.5 — penyesuaian stok. Finalisasi/approve/void adalah Action khusus
 * (`StockAdjustmentsTable`), bukan `EditRecord::save()` — sama pola dengan
 * `StockOpnameResource` (T3.4).
 */
class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Penyesuaian Stok';

    protected static ?string $modelLabel = 'Penyesuaian Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventaris';

    public static function form(Schema $schema): Schema
    {
        return StockAdjustmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockAdjustmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockAdjustments::route('/'),
            'create' => CreateStockAdjustment::route('/create'),
            'edit' => EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
