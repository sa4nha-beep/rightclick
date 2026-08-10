<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Branches\Pages;

use App\Presentation\Filament\Resources\Branches\BranchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;
}
