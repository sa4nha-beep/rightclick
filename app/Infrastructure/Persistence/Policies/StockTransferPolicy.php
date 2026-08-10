<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockTransfer;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi dokumen kirim transfer (T3.6). `view_transfer_history` ATAU
 * `perform_transfer` membuka akses baca — keduanya sudah dimiliki
 * Gudang/Admin/Owner (`PermissionSeeder`).
 *
 * `void()` memeriksa TIDAK ADA receipt aktif — dicek di
 * `VoidStockTransferAction` (butuh query relasi, bukan sekadar
 * perbandingan state seperti Policy lain) DAN di sini sebagai gerbang UI
 * (tombol tidak muncul bila receipt masih ada).
 */
class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_transfer_history') || $user->can('perform_transfer');
    }

    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('view_transfer_history') || $user->can('perform_transfer');
    }

    public function create(User $user): bool
    {
        return $user->can('perform_transfer');
    }

    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('perform_transfer') && $stockTransfer->state === DocumentState::Draft;
    }

    public function delete(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('perform_transfer') && $stockTransfer->state === DocumentState::Draft;
    }

    public function restore(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('perform_transfer');
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, StockTransfer $stockTransfer): bool
    {
        return false;
    }

    public function dispatch(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('perform_transfer') && $stockTransfer->state === DocumentState::Draft;
    }

    public function void(User $user, StockTransfer $stockTransfer): bool
    {
        if (! $user->can('void_stock_document')) {
            return false;
        }

        if ($stockTransfer->state !== DocumentState::Final) {
            return false;
        }

        $receipt = $stockTransfer->receipt;

        return $receipt === null || $receipt->state === DocumentState::Void;
    }
}
