<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi faktur pembelian (T5.2). Lihat catatan disambiguasi permission
 * di `PermissionSeeder`/`GoodsReceiptPolicy`.
 *
 * SELURUH aksi tulis (create/update/delete/finalize/void) digerbang
 * `approve_goods_receipt` SAJA — dokumen ini adalah tindakan Admin/Owner
 * mencatat sisi finansial/AP formal, tidak punya jenjang "buat vs setujui"
 * terpisah seperti PO (TH4) karena TIDAK ADA alur ambang di sini (§10
 * tidak menetapkan TH untuk faktur pembelian) — beda dari
 * `PurchaseOrderPolicy` yang memisah `create_purchase_order` (finalize)
 * dari `approve_purchase_order` (menyetujui Approval tertunda).
 */
class PurchaseInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_goods_receipt');
    }

    public function view(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $user->can('view_goods_receipt');
    }

    public function create(User $user): bool
    {
        return $user->can('approve_goods_receipt');
    }

    public function update(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $user->can('approve_goods_receipt') && $purchaseInvoice->state === DocumentState::Draft;
    }

    public function delete(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $user->can('approve_goods_receipt') && $purchaseInvoice->state === DocumentState::Draft;
    }

    public function restore(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $user->can('approve_goods_receipt');
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return false;
    }

    public function finalize(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $user->can('approve_goods_receipt') && $purchaseInvoice->state === DocumentState::Draft;
    }

    public function void(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $user->can('approve_goods_receipt') && $purchaseInvoice->state === DocumentState::Final;
    }
}
