<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Concerns\GuardsMasterDataWrites;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\User;

/**
 * `products` adalah tabel REPLICATED (CLAUDE.md §7) — HQ satu-satunya
 * penulis, node cabang membaca replika read-only. Setiap write ability
 * di bawah menolak node cabang sebagai kompensasi lapis aplikasi (M02),
 * sama seperti `BranchPolicy`/`PartnerPolicy`.
 *
 * BELUM ditegakkan di sini — celah yang diketahui, bukan kelalaian:
 * TH5a/TH5b/TH5c (CLAUDE.md §10) mensyaratkan approval Owner untuk
 * kenaikan harga jual >10%, penurunan >5%, atau harga di bawah HPP batch
 * tertua. Ability `update()` di bawah HANYA memeriksa `edit_products` —
 * TIDAK membandingkan `selling_price` lama vs baru, tidak memanggil
 * `ApprovalService` (T1.9). Perbandingan harga terhadap HPP batch tertua
 * juga tidak mungkin dari Policy ini karena `stock_batches` belum ada
 * (T3.1). Penegakan TH5a-c adalah pekerjaan lapisan Service/Action
 * (mis. `ProductPriceChangeAction`) yang dipanggil dari Filament Resource
 * (T2.9) atau task Inventory (T3), bukan tanggung jawab Policy model.
 *
 * `manage_product_prices`, `manage_product_stock`, `manage_product_variants`,
 * `manage_product_discontinue` (PermissionSeeder, T1.5) belum digunakan —
 * reserved untuk fitur granular yang belum dibangun (gating field harga
 * khusus, aksi stok dari halaman produk, varian produk, alur discontinue
 * eksplisit). Tidak ada satu pun yang dikonfirmasi dalam scope MVP saat
 * ini di luar nama permission-nya sendiri.
 */
class ProductPolicy
{
    use GuardsMasterDataWrites;

    public function viewAny(User $user): bool
    {
        return $user->can('view_products');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('view_products');
    }

    public function create(User $user): bool
    {
        return $user->can('create_products') && $this->nodeCanWriteMasterData();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('edit_products') && $this->nodeCanWriteMasterData();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('delete_products') && $this->nodeCanWriteMasterData();
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can('delete_products') && $this->nodeCanWriteMasterData();
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }
}
