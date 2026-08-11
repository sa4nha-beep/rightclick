<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\CashierShiftCount;
use App\Infrastructure\Persistence\Models\User;

/**
 * Baris hitung kas per pecahan tidak punya API tulis independen — hanya
 * dimanipulasi lewat `CloseCashierShiftAction`, yang otorisasinya sudah
 * ditegakkan `CashierShiftPolicy::close()`. create/update/delete di sini
 * SELALU `false`, sama pola dengan `StockOpnameLinePolicy`.
 */
class CashierShiftCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_cashier_shift') || $user->can('close_cashier_shift');
    }

    public function view(User $user, CashierShiftCount $cashierShiftCount): bool
    {
        return $user->can('view_cashier_shift') || $user->can('close_cashier_shift');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CashierShiftCount $cashierShiftCount): bool
    {
        return false;
    }

    public function delete(User $user, CashierShiftCount $cashierShiftCount): bool
    {
        return false;
    }

    public function restore(User $user, CashierShiftCount $cashierShiftCount): bool
    {
        return false;
    }

    public function forceDelete(User $user, CashierShiftCount $cashierShiftCount): bool
    {
        return false;
    }
}
