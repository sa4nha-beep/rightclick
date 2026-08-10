<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockMutations\Pages;

use App\Presentation\Filament\Resources\StockMutations\StockMutationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStockMutation extends ViewRecord
{
    protected static string $resource = StockMutationResource::class;
}
