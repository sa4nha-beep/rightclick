<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi purchase order (T5.1). Sama pola dengan `SalePolicy`:
 * `finalize()` digerbang `create_purchase_order` (BUKAN `edit_purchase_order`)
 * — mengajukan PO adalah pekerjaan yang sama dengan menyelesaikannya, nilai
 * TH4 (T5.1) adalah logika bisnis di `FinalizePurchaseOrderAction`, bukan
 * otorisasi — sama pola dengan `StockAdjustmentPolicy::finalize()`.
 *
 * `void()` digerbang `void_purchase_order` — permission BARU, TIDAK ada di
 * 6 permission Procurement yang diseed T1.5 (`view_purchase_orders`,
 * `create_purchase_order`, `edit_purchase_order`, `approve_purchase_order`,
 * `view_goods_receipt`, `approve_goods_receipt`). Gap yang sama polanya
 * dengan `view_stock_cost` (T3.3): `edit_purchase_order` menggerbang
 * penyuntingan DRAFT, bukan pembatalan dokumen FINAL — tidak ada permission
 * seeded yang tepat untuk itu, beda dari Sales (`void_sale`) atau Inventory
 * (`void_stock_document`) yang sudah punya permission void dedicated
 * sejak T1.5. Ditambahkan ke `PermissionSeeder` di commit yang sama,
 * Procurement menjadi 7 permission — direkonsiliasi terhadap
 * `HS-PERM-RIGHTCLICK-v1.2` bila tersedia.
 *
 * `approve()` — ability terpisah untuk memutuskan Approval yang tertaut
 * dokumen ini, digerbang `approve_purchase_order` (permission yang SUDAH
 * ada sejak T1.5, cocok dipakai langsung — beda dari `void_purchase_order`
 * yang benar-benar baru) — Admin/Owner saja, sama pola
 * `approve_stock_adjustment`/`manage_sale_discount`.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_purchase_orders');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('view_purchase_orders');
    }

    public function create(User $user): bool
    {
        return $user->can('create_purchase_order');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('edit_purchase_order') && $purchaseOrder->state === DocumentState::Draft;
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('edit_purchase_order') && $purchaseOrder->state === DocumentState::Draft;
    }

    public function restore(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('edit_purchase_order');
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    public function finalize(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('create_purchase_order') && $purchaseOrder->state === DocumentState::Draft;
    }

    public function void(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('void_purchase_order') && $purchaseOrder->state === DocumentState::Final;
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('approve_purchase_order') && $purchaseOrder->state === DocumentState::Draft;
    }
}
