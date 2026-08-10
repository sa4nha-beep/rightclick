<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashEntries\Pages;

use App\Presentation\Filament\Resources\CashEntries\CashEntryResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tanpa header action "Buat baru" — append-only (AC-21), satu-satunya
 * jalur tulis adalah `CashLedgerService`.
 */
class ListCashEntries extends ListRecords
{
    protected static string $resource = CashEntryResource::class;
}
