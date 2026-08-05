<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Concerns;

use App\Infrastructure\Persistence\Scopes\BranchScope;
use App\Infrastructure\Persistence\Support\BranchContext;

/**
 * Menandai model sebagai branch-scoped (R12 — "Stok bersifat branch-scoped").
 *
 * Dipakai oleh seluruh model transaksi yang memiliki kolom `branch_id`.
 * Mendaftarkan `BranchScope` secara global dan mengisi `branch_id` otomatis
 * saat pembuatan bila belum diisi eksplisit — mis. saat sinkronisasi
 * menyimpan dokumen milik cabang lain, nilai yang sudah diset tidak ditimpa.
 *
 * Lintas cabang yang disengaja (laporan Owner, konsolidasi HQ) memakai
 * `Model::withoutGlobalScope(BranchScope::class)`, bukan menghapus trait ini.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model): void {
            if (empty($model->branch_id)) {
                $model->branch_id = app(BranchContext::class)->current();
            }
        });
    }
}
