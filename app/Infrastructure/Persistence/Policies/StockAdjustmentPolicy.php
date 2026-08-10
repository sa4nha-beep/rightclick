<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi penyesuaian stok (T3.5). Sama pola dengan `StockOpnamePolicy`:
 * `view_stock_mutations` ATAU `perform_adjustment` membuka akses baca.
 *
 * `finalize()` HANYA memeriksa `perform_adjustment` — keputusan TH3/TH3b
 * (ambang nilai, PT15) adalah logika bisnis di
 * `FinalizeStockAdjustmentAction`, bukan otorisasi: siapa pun yang boleh
 * menyesuaikan stok boleh MENCOBA finalisasi, hasilnya (langsung diterapkan
 * vs. menunggu approval) tergantung nilai dan pelakunya, bukan permission
 * yang berbeda.
 *
 * `approve()` — ability terpisah untuk memutuskan Approval yang tertaut
 * dokumen ini, digerbang `approve_stock_adjustment` (BUKAN `decide_approval`
 * generik `ApprovalPolicy`) — Admin/Owner saja, bukan Gudang (§2).
 */
class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_stock_mutations') || $user->can('perform_adjustment');
    }

    public function view(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('view_stock_mutations') || $user->can('perform_adjustment');
    }

    public function create(User $user): bool
    {
        return $user->can('perform_adjustment');
    }

    public function update(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('perform_adjustment') && $stockAdjustment->state === DocumentState::Draft;
    }

    public function delete(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('perform_adjustment') && $stockAdjustment->state === DocumentState::Draft;
    }

    public function restore(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('perform_adjustment');
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, StockAdjustment $stockAdjustment): bool
    {
        return false;
    }

    public function finalize(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('perform_adjustment') && $stockAdjustment->state === DocumentState::Draft;
    }

    public function void(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('void_stock_document') && $stockAdjustment->state === DocumentState::Final;
    }

    public function approve(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('approve_stock_adjustment') && $stockAdjustment->state === DocumentState::Draft;
    }
}
