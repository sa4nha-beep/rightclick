<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\Receivable;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi baris cache piutang (penutup gap FR-M11a-05). Baris ini murni
 * cache turunan yang diperbarui `FinalizeSaleAction`/`RecordReceivablePaymentAction`/
 * `VoidSaleAction` — TIDAK ADA API tulis independen, pola sama
 * `StockOpnameLinePolicy`.
 */
class ReceivablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_receivables');
    }

    public function view(User $user, Receivable $receivable): bool
    {
        return $user->can('view_receivables');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Receivable $receivable): bool
    {
        return false;
    }

    public function delete(User $user, Receivable $receivable): bool
    {
        return false;
    }

    public function restore(User $user, Receivable $receivable): bool
    {
        return false;
    }

    public function forceDelete(User $user, Receivable $receivable): bool
    {
        return false;
    }
}
