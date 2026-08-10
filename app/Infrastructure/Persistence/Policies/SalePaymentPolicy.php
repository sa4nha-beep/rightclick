<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\SalePayment;
use App\Infrastructure\Persistence\Models\User;

/**
 * Sama pola dengan `StockAdjustmentLinePolicy` — tanpa API tulis independen.
 */
class SalePaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_sales');
    }

    public function view(User $user, SalePayment $salePayment): bool
    {
        return $user->can('view_sales');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SalePayment $salePayment): bool
    {
        return false;
    }

    public function delete(User $user, SalePayment $salePayment): bool
    {
        return false;
    }

    public function restore(User $user, SalePayment $salePayment): bool
    {
        return false;
    }

    public function forceDelete(User $user, SalePayment $salePayment): bool
    {
        return false;
    }
}
