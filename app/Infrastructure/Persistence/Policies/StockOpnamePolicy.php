<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Inventory\Enums\StockOpnameType;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockOpname;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi stock opname (T3.4). `view_stock_mutations` ATAU `perform_opname`
 * membuka akses baca — dua-duanya sudah dimiliki Gudang/Admin/Owner/Viewer
 * (`PermissionSeeder`), tanpa perlu permission baru khusus dokumen ini.
 *
 * `finalize()`/`void()` adalah custom ability (bukan `update()`/`delete()`
 * standar) — dipanggil Filament Action lewat `Gate::authorize()` eksplisit,
 * karena aturannya berbeda dari CRUD biasa:
 *  - `finalize()`: `type=opening_balance` (R9) mensyaratkan
 *    `adjust_opening_balance` TAMBAHAN, bukan pengganti `perform_opname` —
 *    Gudang boleh opname berkala tapi tidak boleh menetapkan saldo awal.
 *  - `void()`: permission `void_stock_document` terpisah dari
 *    `perform_opname` — Gudang bisa membuat/finalisasi opname tapi TIDAK
 *    bisa membatalkannya sendiri (§2 — "tidak mengubah stok tanpa alasan
 *    tercatat", void adalah koreksi yang perlu wewenang lebih tinggi).
 */
class StockOpnamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_stock_mutations') || $user->can('perform_opname');
    }

    public function view(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('view_stock_mutations') || $user->can('perform_opname');
    }

    public function create(User $user): bool
    {
        return $user->can('perform_opname');
    }

    public function update(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('perform_opname') && $stockOpname->state === DocumentState::Draft;
    }

    public function delete(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('perform_opname') && $stockOpname->state === DocumentState::Draft;
    }

    public function restore(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('perform_opname');
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, StockOpname $stockOpname): bool
    {
        return false;
    }

    public function finalize(User $user, StockOpname $stockOpname): bool
    {
        if (! $user->can('perform_opname')) {
            return false;
        }

        if ($stockOpname->type === StockOpnameType::OpeningBalance && ! $user->can('adjust_opening_balance')) {
            return false;
        }

        return $stockOpname->state === DocumentState::Draft;
    }

    public function void(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('void_stock_document') && $stockOpname->state === DocumentState::Final;
    }
}
