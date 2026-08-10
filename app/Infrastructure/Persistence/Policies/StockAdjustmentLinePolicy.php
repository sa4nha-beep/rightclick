<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\StockAdjustmentLine;
use App\Infrastructure\Persistence\Models\User;

/**
 * Sama pola dengan `StockOpnameLinePolicy` — tanpa API tulis independen.
 */
class StockAdjustmentLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_stock_mutations') || $user->can('perform_adjustment');
    }

    public function view(User $user, StockAdjustmentLine $stockAdjustmentLine): bool
    {
        return $user->can('view_stock_mutations') || $user->can('perform_adjustment');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockAdjustmentLine $stockAdjustmentLine): bool
    {
        return false;
    }

    public function delete(User $user, StockAdjustmentLine $stockAdjustmentLine): bool
    {
        return false;
    }

    public function restore(User $user, StockAdjustmentLine $stockAdjustmentLine): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockAdjustmentLine $stockAdjustmentLine): bool
    {
        return false;
    }
}
