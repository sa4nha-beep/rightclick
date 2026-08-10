<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockMutations\Pages;

use App\Presentation\Filament\Resources\StockMutations\StockMutationResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tanpa header action "Buat baru" — append-only (R1), satu-satunya jalur
 * tulis adalah `StockLedgerService`.
 */
class ListStockMutations extends ListRecords
{
    protected static string $resource = StockMutationResource::class;
}
