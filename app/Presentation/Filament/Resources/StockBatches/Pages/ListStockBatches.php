<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBatches\Pages;

use App\Presentation\Filament\Resources\StockBatches\StockBatchResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tanpa header action "Buat baru" — batch hanya lahir dari
 * `StockLedgerService` (R1), tidak pernah lewat panel.
 */
class ListStockBatches extends ListRecords
{
    protected static string $resource = StockBatchResource::class;
}
