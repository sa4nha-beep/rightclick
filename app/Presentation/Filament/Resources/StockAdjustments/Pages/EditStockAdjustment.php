<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockAdjustments\Pages;

use App\Presentation\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Resources\Pages\EditRecord;

class EditStockAdjustment extends EditRecord
{
    protected static string $resource = StockAdjustmentResource::class;
}
