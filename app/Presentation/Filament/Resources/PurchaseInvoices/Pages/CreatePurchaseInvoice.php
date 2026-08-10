<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseInvoices\Pages;

use App\Presentation\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;
}
