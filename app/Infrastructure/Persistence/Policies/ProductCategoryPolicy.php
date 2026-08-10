<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Concerns\GuardsMasterDataWrites;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Infrastructure\Persistence\Models\User;

/**
 * `product_categories` adalah tabel REPLICATED (CLAUDE.md §7) — HQ
 * satu-satunya penulis, node cabang membaca replika read-only. Setiap
 * write ability di bawah menolak node cabang sebagai kompensasi lapis
 * aplikasi (M02), sama seperti `BranchPolicy`/`PartnerPolicy`.
 *
 * Tidak ada grup permission `*_categories` terpisah pada matriks 58
 * permission (PermissionSeeder, T1.5) — kategori adalah atribut pendukung
 * produk, karenanya memakai permission `*_products` yang sudah ada.
 */
class ProductCategoryPolicy
{
    use GuardsMasterDataWrites;

    public function viewAny(User $user): bool
    {
        return $user->can('view_products');
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('view_products');
    }

    public function create(User $user): bool
    {
        return $user->can('create_products') && $this->nodeCanWriteMasterData();
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('edit_products') && $this->nodeCanWriteMasterData();
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('delete_products') && $this->nodeCanWriteMasterData();
    }

    public function restore(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('delete_products') && $this->nodeCanWriteMasterData();
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, ProductCategory $productCategory): bool
    {
        return false;
    }
}
