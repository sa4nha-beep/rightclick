<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBatches\Pages;

use App\Presentation\Filament\Resources\StockBatches\StockBatchResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStockBatch extends ViewRecord
{
    protected static string $resource = StockBatchResource::class;
}
