<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\StockBalance;
use App\Infrastructure\Persistence\Models\User;

/**
 * `stock_balances` — cache turunan (LOCAL, T3.2), ditulis `StockLedgerService`
 * atau `php artisan stock:rebuild-balances`. Tidak pernah lewat panel:
 * create/update/delete/restore/forceDelete SELALU `false`.
 *
 * `view_stock` — bukan `view_batches`/`view_stock_mutations` — karena baris
 * ini hanya kuantitas, tanpa `unit_cost`. Ini bacaan yang aman untuk Kasir
 * (POS-05 lencana "HABIS") dan Gudang (§2 — melihat kuantitas, bukan nilai).
 */
class StockBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_stock');
    }

    public function view(User $user, StockBalance $stockBalance): bool
    {
        return $user->can('view_stock');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockBalance $stockBalance): bool
    {
        return false;
    }

    public function delete(User $user, StockBalance $stockBalance): bool
    {
        return false;
    }

    public function restore(User $user, StockBalance $stockBalance): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockBalance $stockBalance): bool
    {
        return false;
    }
}
