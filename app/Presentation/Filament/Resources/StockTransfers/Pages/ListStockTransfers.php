<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockTransfers\Pages;

use App\Presentation\Filament\Resources\StockTransfers\StockTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockTransfers extends ListRecords
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
