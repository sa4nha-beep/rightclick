<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\CashEntry;
use App\Infrastructure\Persistence\Models\User;

/**
 * `cash_entries` — ledger append-only, satu-satunya jalur tulis adalah
 * `CashLedgerService` (T5.4). Sama pola `StockMutationPolicy`:
 * create/update/delete/restore/forceDelete SELALU `false`.
 */
class CashEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_cash_entries');
    }

    public function view(User $user, CashEntry $cashEntry): bool
    {
        return $user->can('view_cash_entries');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CashEntry $cashEntry): bool
    {
        return false;
    }

    public function delete(User $user, CashEntry $cashEntry): bool
    {
        return false;
    }

    public function restore(User $user, CashEntry $cashEntry): bool
    {
        return false;
    }

    public function forceDelete(User $user, CashEntry $cashEntry): bool
    {
        return false;
    }
}
