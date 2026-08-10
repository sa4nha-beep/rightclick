<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBalances\Pages;

use App\Presentation\Filament\Resources\StockBalances\StockBalanceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStockBalance extends ViewRecord
{
    protected static string $resource = StockBalanceResource::class;
}
