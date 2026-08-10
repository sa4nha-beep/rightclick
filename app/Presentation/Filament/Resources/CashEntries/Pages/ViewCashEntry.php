<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashEntries\Pages;

use App\Presentation\Filament\Resources\CashEntries\CashEntryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCashEntry extends ViewRecord
{
    protected static string $resource = CashEntryResource::class;
}
