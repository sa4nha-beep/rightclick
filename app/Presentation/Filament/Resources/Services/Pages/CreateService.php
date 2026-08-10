<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Services\Pages;

use App\Presentation\Filament\Resources\Services\ServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;
}
