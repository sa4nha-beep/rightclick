<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\User;

/**
 * Audit log: read-only jejak permanen (R11, P1).
 *
 * `view_audit_logs` (Owner, Admin terbatas cabangnya via BranchScope pada
 * model AuditLog) dan `export_audit_logs` (Owner saja) diseed T1.14 —
 * HS-PERM-RIGHTCLICK-v1.2 §3.1/§4.1.
 *
 * update/delete/forceDelete SELALU false tanpa syarat, tidak bergantung
 * permission apa pun — P1: "Tidak ada peran yang dapat update atau delete
 * audit_logs, termasuk Owner". Diuji T1.13 (PT6).
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_audit_logs');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->can('view_audit_logs');
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
