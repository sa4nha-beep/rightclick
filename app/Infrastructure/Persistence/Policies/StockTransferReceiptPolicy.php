<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockTransferReceipt;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi dokumen terima transfer (T3.6). Sama pola dengan
 * `StockTransferPolicy`.
 */
class StockTransferReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_transfer_history') || $user->can('perform_transfer');
    }

    public function view(User $user, StockTransferReceipt $stockTransferReceipt): bool
    {
        return $user->can('view_transfer_history') || $user->can('perform_transfer');
    }

    /**
     * Dibuat HANYA lewat `ReceiveStockTransferAction` (setelah validasi
     * dokumen kirim sudah final & belum diterima) — tidak ada form create
     * bebas di panel (`StockTransferReceiptResource` tidak mendaftarkan
     * halaman create).
     */
    public function create(User $user): bool
    {
        return $user->can('perform_transfer');
    }

    public function update(User $user, StockTransferReceipt $stockTransferReceipt): bool
    {
        return false;
    }

    public function delete(User $user, StockTransferReceipt $stockTransferReceipt): bool
    {
        return false;
    }

    public function restore(User $user, StockTransferReceipt $stockTransferReceipt): bool
    {
        return $user->can('perform_transfer');
    }

    public function forceDelete(User $user, StockTransferReceipt $stockTransferReceipt): bool
    {
        return false;
    }

    public function void(User $user, StockTransferReceipt $stockTransferReceipt): bool
    {
        return $user->can('void_stock_document') && $stockTransferReceipt->state === DocumentState::Final;
    }
}
