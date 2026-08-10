<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseOrders\Pages;

use App\Presentation\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
}
