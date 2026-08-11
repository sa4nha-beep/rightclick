<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Receivables\Pages;

use App\Presentation\Filament\Resources\Receivables\ReceivableResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tanpa header action "Buat baru" — `receivables` adalah cache turunan
 * (dibuat `FinalizeSaleAction`, diperbarui `RecordReceivablePaymentAction`),
 * tidak pernah diisi manual lewat panel.
 */
class ListReceivables extends ListRecords
{
    protected static string $resource = ReceivableResource::class;
}
