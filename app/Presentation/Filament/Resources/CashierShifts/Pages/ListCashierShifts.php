<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashierShifts\Pages;

use App\Presentation\Filament\Resources\CashierShifts\CashierShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashierShifts extends ListRecords
{
    protected static string $resource = CashierShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
