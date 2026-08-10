<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockTransfers\Pages;

use App\Presentation\Filament\Resources\StockTransfers\StockTransferResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockTransfer extends CreateRecord
{
    protected static string $resource = StockTransferResource::class;
}
