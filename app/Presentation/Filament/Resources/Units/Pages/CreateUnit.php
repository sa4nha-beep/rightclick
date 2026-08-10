<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Units\Pages;

use App\Presentation\Filament\Resources\Units\UnitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUnit extends CreateRecord
{
    protected static string $resource = UnitResource::class;
}
