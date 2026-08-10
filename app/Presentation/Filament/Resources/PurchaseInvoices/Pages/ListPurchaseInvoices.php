<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseInvoices\Pages;

use App\Presentation\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
