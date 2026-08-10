<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\SaleReturns\Pages;

use App\Presentation\Filament\Resources\SaleReturns\SaleReturnResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSaleReturn extends CreateRecord
{
    protected static string $resource = SaleReturnResource::class;
}
