<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\SaleReturnLine;
use App\Infrastructure\Persistence\Models\User;

/**
 * Sama pola dengan `StockAdjustmentLinePolicy` — tanpa API tulis independen.
 */
class SaleReturnLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_sale_returns');
    }

    public function view(User $user, SaleReturnLine $saleReturnLine): bool
    {
        return $user->can('view_sale_returns');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SaleReturnLine $saleReturnLine): bool
    {
        return false;
    }

    public function delete(User $user, SaleReturnLine $saleReturnLine): bool
    {
        return false;
    }

    public function restore(User $user, SaleReturnLine $saleReturnLine): bool
    {
        return false;
    }

    public function forceDelete(User $user, SaleReturnLine $saleReturnLine): bool
    {
        return false;
    }
}
