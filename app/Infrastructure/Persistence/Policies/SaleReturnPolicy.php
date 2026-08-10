<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\SaleReturn;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi retur penjualan (T4.3). `create`/`update`/`delete` (draft)
 * digerbang `create_sale_return` — Kasir MEMILIKI permission ini
 * (PermissionSeeder), boleh mengajukan retur.
 *
 * `finalize()`/`void()` digerbang `process_sale_return` — permission ini
 * SENGAJA TIDAK dimiliki Kasir (hanya Admin/Owner via filter "semua
 * kecuali delete_/manage_") — kontrol anti-fraud retail standar: kasir
 * mengajukan, atasan yang benar-benar mencairkannya ke ledger. Sama pola
 * pemisahan `create_sale`/`void_sale` pada `SalePolicy`.
 */
class SaleReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_sale_returns');
    }

    public function view(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('view_sale_returns');
    }

    public function create(User $user): bool
    {
        return $user->can('create_sale_return');
    }

    public function update(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('create_sale_return') && $saleReturn->state === DocumentState::Draft;
    }

    public function delete(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('create_sale_return') && $saleReturn->state === DocumentState::Draft;
    }

    public function restore(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('create_sale_return');
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, SaleReturn $saleReturn): bool
    {
        return false;
    }

    public function finalize(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('process_sale_return') && $saleReturn->state === DocumentState::Draft;
    }

    public function void(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('process_sale_return') && $saleReturn->state === DocumentState::Final;
    }
}
