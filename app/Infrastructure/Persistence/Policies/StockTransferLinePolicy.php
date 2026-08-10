<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\StockTransferLine;
use App\Infrastructure\Persistence\Models\User;

/**
 * Sama pola dengan `StockOpnameLinePolicy` — tanpa API tulis independen.
 */
class StockTransferLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_transfer_history') || $user->can('perform_transfer');
    }

    public function view(User $user, StockTransferLine $stockTransferLine): bool
    {
        return $user->can('view_transfer_history') || $user->can('perform_transfer');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockTransferLine $stockTransferLine): bool
    {
        return false;
    }

    public function delete(User $user, StockTransferLine $stockTransferLine): bool
    {
        return false;
    }

    public function restore(User $user, StockTransferLine $stockTransferLine): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockTransferLine $stockTransferLine): bool
    {
        return false;
    }
}
