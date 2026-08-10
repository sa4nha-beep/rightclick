<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\SaleReturns\Pages;

use App\Presentation\Filament\Resources\SaleReturns\SaleReturnResource;
use Filament\Resources\Pages\EditRecord;

class EditSaleReturn extends EditRecord
{
    protected static string $resource = SaleReturnResource::class;
}
