<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Scopes;

use App\Infrastructure\Persistence\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Menyaring query ke `branch_id` yang aktif pada `BranchContext`.
 *
 * HS-ARCH-RIGHTCLICK-v1.1 §4.4 — "Branch scope diterapkan sebagai global
 * scope, sehingga query yang lupa memfilter cabang tetap aman secara
 * default." Model yang memakai trait `BelongsToBranch` mendaftarkan scope
 * ini secara otomatis; tidak ada Resource atau Service yang perlu
 * menambahkan `where('branch_id', ...)` secara manual.
 *
 * Bila tidak ada cabang aktif (konteks konsol/artisan, job sistem, atau
 * Owner/HQ yang sengaja melihat lintas cabang) scope tidak menyaring apa
 * pun — kelonggaran ini disengaja untuk konteks non-permintaan-pengguna.
 * Penegakan REPLICATED read-only per node (T2.5) adalah scope terpisah,
 * bukan tanggung jawab kelas ini.
 */
final class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = app(BranchContext::class)->current();

        if ($branchId !== null) {
            $builder->where($model->qualifyColumn('branch_id'), $branchId);
        }
    }
}
