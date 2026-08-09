<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\AuditLogs\Pages;

use App\Presentation\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tanpa header action apa pun — tidak ada "Buat baru" (append-only, T1.6).
 */
class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
