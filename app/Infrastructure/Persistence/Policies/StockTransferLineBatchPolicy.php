<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\StockTransferLineBatch;
use App\Infrastructure\Persistence\Models\User;

/**
 * Rincian batch sumber transfer — ditulis SATU-SATUNYA lewat
 * `DispatchStockTransferAction`. Tanpa API tulis independen apa pun.
 */
class StockTransferLineBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_transfer_history') || $user->can('perform_transfer');
    }

    public function view(User $user, StockTransferLineBatch $stockTransferLineBatch): bool
    {
        return $user->can('view_transfer_history') || $user->can('perform_transfer');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockTransferLineBatch $stockTransferLineBatch): bool
    {
        return false;
    }

    public function delete(User $user, StockTransferLineBatch $stockTransferLineBatch): bool
    {
        return false;
    }

    public function restore(User $user, StockTransferLineBatch $stockTransferLineBatch): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockTransferLineBatch $stockTransferLineBatch): bool
    {
        return false;
    }
}
