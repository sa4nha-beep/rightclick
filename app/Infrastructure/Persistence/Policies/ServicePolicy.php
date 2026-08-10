<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Concerns\GuardsMasterDataWrites;
use App\Infrastructure\Persistence\Models\Service;
use App\Infrastructure\Persistence\Models\User;

/**
 * `services` adalah tabel REPLICATED (CLAUDE.md §7) — HQ satu-satunya
 * penulis, node cabang membaca replika read-only. Setiap write ability
 * di bawah menolak node cabang sebagai kompensasi lapis aplikasi (M02),
 * sama seperti `BranchPolicy`/`ProductPolicy`.
 *
 * Tidak ada grup permission `*_services` terpisah pada matriks 58
 * permission (PermissionSeeder, T1.5) — jasa adalah katalog sellable
 * setara produk (baris POS), karenanya memakai permission `*_products`
 * yang sudah ada.
 */
class ServicePolicy
{
    use GuardsMasterDataWrites;

    public function viewAny(User $user): bool
    {
        return $user->can('view_products');
    }

    public function view(User $user, Service $service): bool
    {
        return $user->can('view_products');
    }

    public function create(User $user): bool
    {
        return $user->can('create_products') && $this->nodeCanWriteMasterData();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->can('edit_products') && $this->nodeCanWriteMasterData();
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->can('delete_products') && $this->nodeCanWriteMasterData();
    }

    public function restore(User $user, Service $service): bool
    {
        return $user->can('delete_products') && $this->nodeCanWriteMasterData();
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, Service $service): bool
    {
        return false;
    }
}
