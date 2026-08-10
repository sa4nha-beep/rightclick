<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\GoodsReceipts\Pages;

use App\Presentation\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;
}
