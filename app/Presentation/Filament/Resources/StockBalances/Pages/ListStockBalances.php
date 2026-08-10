<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBalances\Pages;

use App\Presentation\Filament\Resources\StockBalances\StockBalanceResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tanpa header action "Buat baru" — stock_balances adalah cache turunan,
 * tidak pernah diisi manual lewat panel.
 */
class ListStockBalances extends ListRecords
{
    protected static string $resource = StockBalanceResource::class;
}
