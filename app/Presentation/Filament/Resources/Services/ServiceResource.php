<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Services;

use App\Infrastructure\Persistence\Models\Service;
use App\Presentation\Filament\Resources\Services\Pages\CreateService;
use App\Presentation\Filament\Resources\Services\Pages\EditService;
use App\Presentation\Filament\Resources\Services\Pages\ListServices;
use App\Presentation\Filament\Resources\Services\Schemas\ServiceForm;
use App\Presentation\Filament\Resources\Services\Tables\ServicesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T2.9 — Jasa. `services` REPLICATED (CLAUDE.md §7); `ServicePolicy`
 * (T2.7) menegakkan HQ-only write.
 *
 * PENTING — katalog harga jasa untuk baris POS, BUKAN modul "Servis"
 * (tiket/penjadwalan) yang di luar MVP. Lihat catatan di migration T2.7.
 */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Jasa';

    protected static ?string $modelLabel = 'Jasa';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
