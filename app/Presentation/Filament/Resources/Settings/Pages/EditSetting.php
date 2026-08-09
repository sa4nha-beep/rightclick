<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Settings\Pages;

use App\Presentation\Filament\Resources\Settings\SettingResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Tanpa tombol hapus — menghapus kunci ambang akan membisukan pembacaan
 * `Setting::get($key)` di modul lain tanpa pesan galat (lihat dokblok
 * SettingResource). `SettingPolicy::delete()` tetap ada untuk jalur non-UI
 * (mis. artisan tinker darurat oleh Owner), tapi panel tidak menawarkannya.
 */
class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;
}
