<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\ReceivablePayment;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi pelunasan piutang (T5.5). Pola PERSIS `PurchasePaymentPolicy` —
 * `create()` sungguhan digerbang `record_cash_entry` (BUKAN selalu
 * `false`), karena `ReceivablePayment` ditulis langsung lewat
 * `RecordReceivablePaymentAction`, bukan byproduct finalisasi dokumen
 * induk.
 *
 * `viewAny`/`view` digerbang `view_receivables` (BUKAN `view_sales`) —
 * mencerminkan kolom "hutang/piutang" pada matriks §10, bukan akses
 * penjualan secara umum.
 *
 * `update`/`delete`/`restore` tetap `false` — pembayaran immutable begitu
 * tercatat, tanpa mekanisme koreksi individual di T5.5.
 */
class ReceivablePaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_receivables');
    }

    public function view(User $user, ReceivablePayment $receivablePayment): bool
    {
        return $user->can('view_receivables');
    }

    public function create(User $user): bool
    {
        return $user->can('record_cash_entry');
    }

    public function update(User $user, ReceivablePayment $receivablePayment): bool
    {
        return false;
    }

    public function delete(User $user, ReceivablePayment $receivablePayment): bool
    {
        return false;
    }

    public function restore(User $user, ReceivablePayment $receivablePayment): bool
    {
        return false;
    }

    public function forceDelete(User $user, ReceivablePayment $receivablePayment): bool
    {
        return false;
    }
}
