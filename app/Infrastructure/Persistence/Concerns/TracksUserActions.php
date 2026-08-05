<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Mengisi `created_by` dan `updated_by` secara otomatis.
 *
 * DB Design 1 — "Jejak pengguna: created_by, updated_by pada seluruh tabel
 * transaksi." Diisi dari pengguna terautentikasi saat model dibuat/diubah.
 *
 * `created_by` dibiarkan kosong bila tidak ada pengguna terautentikasi
 * (mis. seeder `branches` sebelum akun Owner ada, DB Design §9.2 urutan 1
 * mendahului urutan 3) — kolom karenanya nullable pada migration, bukan
 * dipaksa terisi nilai sistem palsu yang akan mengotori audit trail.
 *
 * Nilai yang sudah diset eksplisit (mis. saat sinkronisasi menerima dokumen
 * dari node lain dan ingin mempertahankan `created_by` aslinya) tidak
 * ditimpa.
 */
trait TracksUserActions
{
    public static function bootTracksUserActions(): void
    {
        static::creating(function ($model): void {
            if (! $model->isDirty('created_by')) {
                $model->created_by = Auth::id();
            }

            if (! $model->isDirty('updated_by')) {
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model): void {
            if (! $model->isDirty('updated_by')) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
