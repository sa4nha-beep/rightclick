<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockOpnames\Pages;

use App\Presentation\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockOpnames extends ListRecords
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
