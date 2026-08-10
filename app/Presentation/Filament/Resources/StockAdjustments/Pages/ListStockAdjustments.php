<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockAdjustments\Pages;

use App\Presentation\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockAdjustments extends ListRecords
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
