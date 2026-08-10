<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\PurchasePayment;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi pembayaran hutang (T5.3). BEDA dari `SalePaymentPolicy` —
 * `create()` sungguhan digerbang `record_cash_entry` (BUKAN selalu
 * `false`), karena `PurchasePayment` ditulis langsung lewat
 * `RecordPurchasePaymentAction`, bukan sebagai byproduct finalisasi
 * dokumen induk (lihat docblock model).
 *
 * `update`/`delete`/`restore` tetap `false` — pembayaran immutable begitu
 * tercatat, tanpa mekanisme koreksi individual di T5.3.
 */
class PurchasePaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_payables');
    }

    public function view(User $user, PurchasePayment $purchasePayment): bool
    {
        return $user->can('view_payables');
    }

    public function create(User $user): bool
    {
        return $user->can('record_cash_entry');
    }

    public function update(User $user, PurchasePayment $purchasePayment): bool
    {
        return false;
    }

    public function delete(User $user, PurchasePayment $purchasePayment): bool
    {
        return false;
    }

    public function restore(User $user, PurchasePayment $purchasePayment): bool
    {
        return false;
    }

    public function forceDelete(User $user, PurchasePayment $purchasePayment): bool
    {
        return false;
    }
}
