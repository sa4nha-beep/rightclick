<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\SaleReturns\Pages;

use App\Presentation\Filament\Resources\SaleReturns\SaleReturnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSaleReturns extends ListRecords
{
    protected static string $resource = SaleReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
