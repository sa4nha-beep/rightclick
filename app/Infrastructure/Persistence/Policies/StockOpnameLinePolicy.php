<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\StockOpnameLine;
use App\Infrastructure\Persistence\Models\User;

/**
 * Baris opname tidak punya API tulis independen — hanya dimanipulasi lewat
 * repeater form dokumen induk (`StockOpname`), yang otorisasinya sudah
 * ditegakkan `StockOpnamePolicy`. create/update/delete di sini SELALU
 * `false`, sama pola dengan `StockBatchPolicy`.
 */
class StockOpnameLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_stock_mutations') || $user->can('perform_opname');
    }

    public function view(User $user, StockOpnameLine $stockOpnameLine): bool
    {
        return $user->can('view_stock_mutations') || $user->can('perform_opname');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockOpnameLine $stockOpnameLine): bool
    {
        return false;
    }

    public function delete(User $user, StockOpnameLine $stockOpnameLine): bool
    {
        return false;
    }

    public function restore(User $user, StockOpnameLine $stockOpnameLine): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockOpnameLine $stockOpnameLine): bool
    {
        return false;
    }
}
