<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\User;

/**
 * Audit log: read-only jejak permanen (R11, P1).
 *
 * Tidak ada permission `view_audit_logs` di T1.5/T1.9 (baru direncanakan T1.14).
 * Sebelum itu, akses ke audit log masih terbatas internal (logging aksi sendiri,
 * pengecekan access_denied saat rejection, dst.) tanpa UI publik.
 *
 * Policy ini wajib ada (P4 — architecture test mewajibkan policy per model),
 * tetapi semua aksi selalu false sampai permission `view_audit_logs` tersedia.
 *
 * Update/delete SELALU false, termasuk untuk Owner (P1).
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        // Placeholder: true hanya jika view_audit_logs permission ada (direncanakan T1.14)
        // Saat ini: false — audit log bukan untuk UI publik di Fase 1
        return false;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
