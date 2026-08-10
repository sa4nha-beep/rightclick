<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseInvoices\Pages;

use App\Presentation\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;
}
