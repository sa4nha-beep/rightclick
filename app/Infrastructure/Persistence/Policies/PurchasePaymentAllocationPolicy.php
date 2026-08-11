<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\PurchasePaymentAllocation;
use App\Infrastructure\Persistence\Models\User;

/**
 * Sisi AP dari `ReceivablePaymentAllocationPolicy` — treatment simetris
 * penuh.
 */
class PurchasePaymentAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_payables');
    }

    public function view(User $user, PurchasePaymentAllocation $purchasePaymentAllocation): bool
    {
        return $user->can('view_payables');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PurchasePaymentAllocation $purchasePaymentAllocation): bool
    {
        return false;
    }

    public function delete(User $user, PurchasePaymentAllocation $purchasePaymentAllocation): bool
    {
        return false;
    }

    public function restore(User $user, PurchasePaymentAllocation $purchasePaymentAllocation): bool
    {
        return false;
    }

    public function forceDelete(User $user, PurchasePaymentAllocation $purchasePaymentAllocation): bool
    {
        return false;
    }
}
