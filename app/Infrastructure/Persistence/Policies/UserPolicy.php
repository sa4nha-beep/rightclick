<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Concerns\GuardsMasterDataWrites;
use App\Infrastructure\Persistence\Models\User;

/**
 * `users` adalah tabel REPLICATED (CLAUDE.md §7) — HQ satu-satunya
 * penulis. Pengecualian sengaja: manageEmergencyDisable() (lihat catatan
 * pada metode itu).
 */
class UserPolicy
{
    use GuardsMasterDataWrites;

    public function viewAny(User $user): bool
    {
        return $user->can('view_users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model) || $user->can('view_users');
    }

    public function create(User $user): bool
    {
        return $user->can('create_users') && $this->nodeCanWriteMasterData();
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('edit_users') && $this->nodeCanWriteMasterData();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('delete_users')
            && $this->nodeCanWriteMasterData()
            && ! $user->is($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can('delete_users') && $this->nodeCanWriteMasterData();
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    public function manageRoles(User $user, User $model): bool
    {
        return $user->can('manage_user_roles') && $this->nodeCanWriteMasterData();
    }

    public function manageBranches(User $user, User $model): bool
    {
        return $user->can('manage_user_branches') && $this->nodeCanWriteMasterData();
    }

    /**
     * `locally_disabled_at` (T1.4) sengaja bisa ditulis di node cabang
     * tanpa menunggu HQ — inilah mekanisme "darurat offline disable":
     * kredensial bocor, HQ tak terjangkau, admin cabang tetap bisa
     * mengunci akun secara lokal. Karena itu ability ini TIDAK melewati
     * nodeCanWriteMasterData().
     */
    public function manageEmergencyDisable(User $user, User $model): bool
    {
        return $user->can('manage_emergency_disable');
    }
}
