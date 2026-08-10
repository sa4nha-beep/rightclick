<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockTransferReceipts\Pages;

use App\Presentation\Filament\Resources\StockTransferReceipts\StockTransferReceiptResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tanpa header action "Buat baru" — receipt hanya lahir dari
 * `ReceiveStockTransferAction`.
 */
class ListStockTransferReceipts extends ListRecords
{
    protected static string $resource = StockTransferReceiptResource::class;
}
