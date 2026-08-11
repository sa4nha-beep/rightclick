<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\Payable;
use App\Infrastructure\Persistence\Models\User;

/**
 * Sisi AP dari `ReceivablePolicy` — treatment simetris penuh.
 */
class PayablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_payables');
    }

    public function view(User $user, Payable $payable): bool
    {
        return $user->can('view_payables');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payable $payable): bool
    {
        return false;
    }

    public function delete(User $user, Payable $payable): bool
    {
        return false;
    }

    public function restore(User $user, Payable $payable): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payable $payable): bool
    {
        return false;
    }
}
