<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Concerns\GuardsMasterDataWrites;
use App\Infrastructure\Persistence\Models\User;
use App\Infrastructure\Persistence\Models\UserBranch;

/**
 * `user_branches` adalah tabel REPLICATED (CLAUDE.md §7) — asosiasi
 * pengguna ke cabang ditulis di HQ. Bukan tabel transaksi (R5 tidak
 * berlaku); tidak memakai soft delete, karenanya tanpa restore/forceDelete.
 */
class UserBranchPolicy
{
    use GuardsMasterDataWrites;

    public function viewAny(User $user): bool
    {
        return $user->can('view_users') || $user->can('manage_user_branches');
    }

    public function view(User $user, UserBranch $userBranch): bool
    {
        return $user->can('view_users') || $user->can('manage_user_branches');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_user_branches') && $this->nodeCanWriteMasterData();
    }

    public function update(User $user, UserBranch $userBranch): bool
    {
        return $user->can('manage_user_branches') && $this->nodeCanWriteMasterData();
    }

    public function delete(User $user, UserBranch $userBranch): bool
    {
        return $user->can('manage_user_branches') && $this->nodeCanWriteMasterData();
    }
}
