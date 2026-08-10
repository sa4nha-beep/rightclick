<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockOpnames\Pages;

use App\Presentation\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockOpname extends CreateRecord
{
    protected static string $resource = StockOpnameResource::class;
}
