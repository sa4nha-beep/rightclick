<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashierShifts\Pages;

use App\Presentation\Filament\Resources\CashierShifts\CashierShiftResource;
use Filament\Resources\Pages\EditRecord;

class EditCashierShift extends EditRecord
{
    protected static string $resource = CashierShiftResource::class;
}
