<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Concerns\GuardsMasterDataWrites;
use App\Infrastructure\Persistence\Models\Employee;
use App\Infrastructure\Persistence\Models\User;

/**
 * `employees` adalah tabel REPLICATED (CLAUDE.md §7) — HQ satu-satunya
 * penulis, node cabang membaca replika read-only. Setiap write ability
 * di bawah menolak node cabang sebagai kompensasi lapis aplikasi (M02),
 * sama seperti `BranchPolicy`/`PartnerPolicy`.
 *
 * Tidak ada grup permission `*_employees` terpisah pada matriks 58
 * permission (PermissionSeeder, T1.5) — data karyawan berdekatan dengan
 * domain Users (akun login), karenanya memakai permission `*_users` yang
 * sudah ada.
 */
class EmployeePolicy
{
    use GuardsMasterDataWrites;

    public function viewAny(User $user): bool
    {
        return $user->can('view_users');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('view_users');
    }

    public function create(User $user): bool
    {
        return $user->can('create_users') && $this->nodeCanWriteMasterData();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('edit_users') && $this->nodeCanWriteMasterData();
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('delete_users') && $this->nodeCanWriteMasterData();
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $user->can('delete_users') && $this->nodeCanWriteMasterData();
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, Employee $employee): bool
    {
        return false;
    }
}
