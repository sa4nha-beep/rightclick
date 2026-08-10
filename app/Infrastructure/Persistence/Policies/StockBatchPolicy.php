<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Models\User;

/**
 * `stock_batches` tidak pernah ditulis lewat panel — satu-satunya jalur
 * tulis adalah `StockLedgerService` (T3.2, R1), dipanggil dari action
 * dokumen (opname, adjustment, penerimaan transfer). create/update/delete
 * di sini SELALU `false`, bukan sekadar dikosongkan.
 *
 * Kolom `unit_cost` disaring di lapisan query (P6), bukan di sini — lihat
 * `StockBatchResource`/`StockBatchesTable` (T3.3) untuk peran tanpa
 * `view_stock_cost`.
 */
class StockBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_batches');
    }

    public function view(User $user, StockBatch $stockBatch): bool
    {
        return $user->can('view_batches');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockBatch $stockBatch): bool
    {
        return false;
    }

    public function delete(User $user, StockBatch $stockBatch): bool
    {
        return false;
    }

    public function restore(User $user, StockBatch $stockBatch): bool
    {
        return false;
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5), dan
     * batch bahkan tidak seharusnya soft-deleted lewat panel sama sekali.
     */
    public function forceDelete(User $user, StockBatch $stockBatch): bool
    {
        return false;
    }
}
