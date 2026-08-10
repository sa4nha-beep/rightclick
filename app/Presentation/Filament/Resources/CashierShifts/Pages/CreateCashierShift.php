<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashierShifts\Pages;

use App\Presentation\Filament\Resources\CashierShifts\CashierShiftResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCashierShift extends CreateRecord
{
    protected static string $resource = CashierShiftResource::class;
}
