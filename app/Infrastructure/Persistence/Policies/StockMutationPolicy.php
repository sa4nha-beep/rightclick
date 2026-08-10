<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\StockMutation;
use App\Infrastructure\Persistence\Models\User;

/**
 * `stock_mutations` — ledger append-only, satu-satunya jalur tulis adalah
 * `StockLedgerService` (R1, T3.2). Sama seperti `AuditLogPolicy`:
 * create/update/delete/restore/forceDelete SELALU `false`.
 *
 * Kolom `unit_cost` disaring di lapisan query (P6) untuk peran tanpa
 * `view_stock_cost` — lihat `StockMutationResource`/`StockMutationsTable`
 * (T3.3).
 */
class StockMutationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_stock_mutations');
    }

    public function view(User $user, StockMutation $stockMutation): bool
    {
        return $user->can('view_stock_mutations');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMutation $stockMutation): bool
    {
        return false;
    }

    public function delete(User $user, StockMutation $stockMutation): bool
    {
        return false;
    }

    public function restore(User $user, StockMutation $stockMutation): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockMutation $stockMutation): bool
    {
        return false;
    }
}
