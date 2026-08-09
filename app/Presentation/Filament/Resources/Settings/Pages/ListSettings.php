<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Settings\Pages;

use App\Presentation\Filament\Resources\Settings\SettingResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tanpa "Buat baru" — kunci ambang diseed, tidak dibuat bebas lewat UI
 * (lihat dokblok SettingResource).
 */
class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;
}
