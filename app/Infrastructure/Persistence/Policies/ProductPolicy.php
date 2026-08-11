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
 * TH5a/TH5b/TH5c (CLAUDE.md §10, penutupan PT16) — DITEGAKKAN, tapi TIDAK
 * di `update()` di bawah. `update()` tetap HANYA memeriksa `edit_products`
 * (izin mengedit record SAMA SEKALI — nama, SKU, kategori, dst.).
 * Perbandingan `selling_price` lama-vs-baru dan HPP batch tertua terjadi
 * di `ChangeProductSellingPriceAction`, dipanggil dari
 * `EditProduct::handleRecordUpdate()` HANYA saat field itu benar-benar
 * berubah — Policy model tidak cocok menampung logika ini karena
 * `update()` menerima state BARU sebagai array `$data` Filament, bukan
 * dua nilai untuk dibandingkan bersama query `stock_batches`.
 *
 * `approve()` (baru) — memutuskan Approval TH5a/TH5b/TH5c yang tertunda,
 * digerbang permission YANG SAMA dengan yang mengizinkan permintaan itu
 * diajukan (`manage_product_prices`) — pola identik `SalePolicy::approve()`
 * (`manage_sale_discount` menggerbang KEDUANYA). Konsekuensi yang sama juga
 * berlaku di sini seperti di Sales: karena Owner DAN Admin sama-sama punya
 * `manage_product_prices`, secara teknis Admin bisa menyetujui permintaan
 * miliknya sendiri — bukan celah baru, karakteristik yang sudah diterima
 * di seluruh sistem approval sejak Fase 4 (tidak ada modul mana pun yang
 * mencegah self-approval lewat permission terpisah).
 *
 * `manage_product_stock`, `manage_product_variants`,
 * `manage_product_discontinue` (PermissionSeeder, T1.5) MASIH belum
 * digunakan — reserved untuk fitur granular yang belum dibangun (aksi
 * stok dari halaman produk, varian produk, alur discontinue eksplisit).
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

    public function approve(User $user, Product $product): bool
    {
        return $user->can('manage_product_prices') && $this->nodeCanWriteMasterData();
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
