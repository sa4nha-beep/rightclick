<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Concerns\GuardsMasterDataWrites;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\User;

/**
 * `branches` adalah tabel REPLICATED (CLAUDE.md §7) — HQ satu-satunya
 * penulis, node cabang membaca replika read-only. Setiap write ability
 * di bawah menolak node cabang sebagai kompensasi lapis aplikasi (M02).
 */
class BranchPolicy
{
    use GuardsMasterDataWrites;

    public function viewAny(User $user): bool
    {
        return $user->can('view_branches');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can('view_branches');
    }

    public function create(User $user): bool
    {
        return $user->can('create_branches') && $this->nodeCanWriteMasterData();
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('edit_branches') && $this->nodeCanWriteMasterData();
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('delete_branches') && $this->nodeCanWriteMasterData();
    }

    public function restore(User $user, Branch $branch): bool
    {
        return $user->can('delete_branches') && $this->nodeCanWriteMasterData();
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, Branch $branch): bool
    {
        return false;
    }
}
