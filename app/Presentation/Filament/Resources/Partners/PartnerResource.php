<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Partners;

use App\Infrastructure\Persistence\Models\Partner;
use App\Presentation\Filament\Resources\Partners\Pages\CreatePartner;
use App\Presentation\Filament\Resources\Partners\Pages\EditPartner;
use App\Presentation\Filament\Resources\Partners\Pages\ListPartners;
use App\Presentation\Filament\Resources\Partners\Schemas\PartnerForm;
use App\Presentation\Filament\Resources\Partners\Tables\PartnersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T2.9 — Pemasok & Pelanggan. `partners` REPLICATED (CLAUDE.md §7);
 * `PartnerPolicy` (T2.2) menegakkan HQ-only write.
 */
class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Pemasok & Pelanggan';

    protected static ?string $modelLabel = 'Mitra';

    protected static ?string $pluralModelLabel = 'Pemasok & Pelanggan';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PartnerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartners::route('/'),
            'create' => CreatePartner::route('/create'),
            'edit' => EditPartner::route('/{record}/edit'),
        ];
    }
}
