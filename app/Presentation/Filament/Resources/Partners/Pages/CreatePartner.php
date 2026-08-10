<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Partners\Pages;

use App\Presentation\Filament\Resources\Partners\PartnerResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePartner extends CreateRecord
{
    protected static string $resource = PartnerResource::class;
}
