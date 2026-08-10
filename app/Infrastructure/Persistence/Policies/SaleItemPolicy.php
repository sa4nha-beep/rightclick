<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\SaleItem;
use App\Infrastructure\Persistence\Models\User;

/**
 * Sama pola dengan `StockAdjustmentLinePolicy` — tanpa API tulis independen.
 */
class SaleItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_sales');
    }

    public function view(User $user, SaleItem $saleItem): bool
    {
        return $user->can('view_sales');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SaleItem $saleItem): bool
    {
        return false;
    }

    public function delete(User $user, SaleItem $saleItem): bool
    {
        return false;
    }

    public function restore(User $user, SaleItem $saleItem): bool
    {
        return false;
    }

    public function forceDelete(User $user, SaleItem $saleItem): bool
    {
        return false;
    }
}
