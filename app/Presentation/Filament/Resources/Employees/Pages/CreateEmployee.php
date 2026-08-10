<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Employees\Pages;

use App\Presentation\Filament\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
}
