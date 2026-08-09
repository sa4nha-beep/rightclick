<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Settings;

use App\Infrastructure\Persistence\Models\Setting;
use App\Presentation\Filament\Resources\Settings\Pages\EditSetting;
use App\Presentation\Filament\Resources\Settings\Pages\ListSettings;
use App\Presentation\Filament\Resources\Settings\Schemas\SettingForm;
use App\Presentation\Filament\Resources\Settings\Tables\SettingsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T1.14 — halaman Pengaturan (TA10, HS-PRD-RIGHTCLICK-v1.0 §5.2).
 *
 * TIDAK ADA halaman create — kunci ambang TH1–TH5c diseed `SettingSeeder`
 * (T1.9); menambah kunci baru sembarangan lewat UI berisiko salah ketik
 * yang membisukan pembacaan ambang di modul lain (`Setting::get($key)`
 * mencocokkan string persis). Hanya List (baca) dan Edit (ubah nilai).
 *
 * Otorisasi lewat `SettingPolicy` — `manage_settings` (Owner saja) +
 * `nodeCanWriteMasterData()` (hanya HQ). Filament menyaring tombol Edit
 * otomatis lewat Policy; di node cabang, halaman ini murni baca meski
 * tombol Edit tidak akan pernah tampil bagi peran mana pun (REPLICATED).
 */
class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $modelLabel = 'Pengaturan';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return SettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettings::route('/'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }
}
