<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Sales\Pages;

use App\Presentation\Filament\Resources\Sales\SaleResource;
use Filament\Resources\Pages\EditRecord;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;
}
