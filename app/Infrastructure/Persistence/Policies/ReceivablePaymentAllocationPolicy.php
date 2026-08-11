<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\ReceivablePaymentAllocation;
use App\Infrastructure\Persistence\Models\User;

/**
 * Baris alokasi tidak punya API tulis independen — hanya dibuat lewat
 * `RecordReceivablePaymentAction`, yang otorisasinya sudah ditegakkan
 * `ReceivablePaymentPolicy::create()`. Pola sama `StockOpnameLinePolicy`.
 */
class ReceivablePaymentAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_receivables');
    }

    public function view(User $user, ReceivablePaymentAllocation $receivablePaymentAllocation): bool
    {
        return $user->can('view_receivables');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ReceivablePaymentAllocation $receivablePaymentAllocation): bool
    {
        return false;
    }

    public function delete(User $user, ReceivablePaymentAllocation $receivablePaymentAllocation): bool
    {
        return false;
    }

    public function restore(User $user, ReceivablePaymentAllocation $receivablePaymentAllocation): bool
    {
        return false;
    }

    public function forceDelete(User $user, ReceivablePaymentAllocation $receivablePaymentAllocation): bool
    {
        return false;
    }
}
