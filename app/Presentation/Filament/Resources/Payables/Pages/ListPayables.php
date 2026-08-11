<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Payables\Pages;

use App\Presentation\Filament\Resources\Payables\PayableResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tanpa header action "Buat baru" — `payables` adalah cache turunan
 * (dibuat `FinalizePurchaseInvoiceAction`, diperbarui
 * `RecordPurchasePaymentAction`), tidak pernah diisi manual lewat panel.
 */
class ListPayables extends ListRecords
{
    protected static string $resource = PayableResource::class;
}
