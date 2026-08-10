<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi penerimaan barang (T5.2, simpul kritis). Lihat catatan
 * disambiguasi permission di `PermissionSeeder` — empat permission
 * (`perform_goods_receipt`/`review_goods_receipt` Inventory,
 * `view_goods_receipt`/`approve_goods_receipt` Procurement) diseed T1.5
 * untuk satu konsep dunia nyata yang sama, dipecah perannya di sini.
 *
 * `finalize()` digerbang `perform_goods_receipt` SAJA — TIDAK ada alur
 * ambang/`ApprovalService` (CLAUDE.md §10 tidak menetapkan TH untuk goods
 * receipt, beda dari PO/TH4). `void()` digerbang `review_goods_receipt`
 * (Admin/Owner meninjau sisi fisik yang dicatat Gudang), BUKAN
 * `perform_goods_receipt` — Gudang yang mencatat penerimaan TIDAK bisa
 * membatalkannya sendiri, sama semangat "Kasir tidak dapat void" (§2)
 * diterapkan ke Gudang untuk dokumen yang sudah menyentuh ledger.
 */
class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_goods_receipt');
    }

    public function view(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can('view_goods_receipt');
    }

    public function create(User $user): bool
    {
        return $user->can('perform_goods_receipt');
    }

    public function update(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can('perform_goods_receipt') && $goodsReceipt->state === DocumentState::Draft;
    }

    public function delete(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can('perform_goods_receipt') && $goodsReceipt->state === DocumentState::Draft;
    }

    public function restore(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can('perform_goods_receipt');
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return false;
    }

    public function finalize(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can('perform_goods_receipt') && $goodsReceipt->state === DocumentState::Draft;
    }

    public function void(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can('review_goods_receipt') && $goodsReceipt->state === DocumentState::Final;
    }
}
