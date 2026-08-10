<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Sales\Pages;

use App\Presentation\Filament\Resources\Sales\SaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;
}
