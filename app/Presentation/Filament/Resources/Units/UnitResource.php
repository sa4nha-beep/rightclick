<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Units;

use App\Infrastructure\Persistence\Models\Unit;
use App\Presentation\Filament\Resources\Units\Pages\CreateUnit;
use App\Presentation\Filament\Resources\Units\Pages\EditUnit;
use App\Presentation\Filament\Resources\Units\Pages\ListUnits;
use App\Presentation\Filament\Resources\Units\Schemas\UnitForm;
use App\Presentation\Filament\Resources\Units\Tables\UnitsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T2.9 — Satuan. `units` REPLICATED (CLAUDE.md §7); `UnitPolicy` (T2.4)
 * menegakkan HQ-only write, memakai permission `*_products`.
 */
class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Satuan';

    protected static ?string $modelLabel = 'Satuan';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UnitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnits::route('/'),
            'create' => CreateUnit::route('/create'),
            'edit' => EditUnit::route('/{record}/edit'),
        ];
    }
}
