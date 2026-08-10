<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\PurchaseOrderLine;
use App\Infrastructure\Persistence\Models\User;

/**
 * Sama pola dengan `SaleItemPolicy`/`StockAdjustmentLinePolicy` — tanpa API
 * tulis independen.
 */
class PurchaseOrderLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_purchase_orders');
    }

    public function view(User $user, PurchaseOrderLine $purchaseOrderLine): bool
    {
        return $user->can('view_purchase_orders');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PurchaseOrderLine $purchaseOrderLine): bool
    {
        return false;
    }

    public function delete(User $user, PurchaseOrderLine $purchaseOrderLine): bool
    {
        return false;
    }

    public function restore(User $user, PurchaseOrderLine $purchaseOrderLine): bool
    {
        return false;
    }

    public function forceDelete(User $user, PurchaseOrderLine $purchaseOrderLine): bool
    {
        return false;
    }
}
