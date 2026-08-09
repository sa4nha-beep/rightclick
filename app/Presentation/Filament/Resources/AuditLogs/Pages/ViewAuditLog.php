<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\AuditLogs\Pages;

use App\Presentation\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Tanpa header action "Ubah" — audit_logs tidak pernah bisa diedit
 * (AuditLogPolicy::update() selalu false, P1).
 */
class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;
}
