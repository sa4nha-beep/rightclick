<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Support;

use Illuminate\Database\Schema\Blueprint;

/**
 * Macro `Blueprint` untuk kolom yang berulang di hampir seluruh 44 tabel
 * MVP (DB Design §1, §4). Didaftarkan sekali dari `AppServiceProvider::boot()`
 * dan dipakai oleh setiap migration mulai T1.4.
 *
 * Sengaja granular, bukan satu macro "buat tabel transaksi" tunggal — tidak
 * semua tabel butuh kombinasi kolom yang sama (mis. `products` REPLICATED
 * tanpa `branch_id`; `audit_logs` dan `stock_mutations` append-only tanpa
 * `updated_at`/`deleted_at`, jadi tidak memakai `userStamps()`/soft delete
 * bawaan sama sekali). Menyembunyikan keputusan itu di balik satu macro besar
 * akan membuat migration yang salah terlihat benar.
 */
final class MigrationMacros
{
    public static function register(): void
    {
        // DB Design §1 — primary key uuid v7, dibangkitkan aplikasi
        // (App\Infrastructure\Persistence\Concerns\HasUuidV7), bukan database.
        Blueprint::macro('uuidPrimaryKey', function (string $column = 'id'): Blueprint {
            /** @var Blueprint $this */
            $this->uuid($column)->primary();

            return $this;
        });

        // DB Design §1 — "created_by uuid, updated_by uuid pada seluruh tabel
        // transaksi." Nullable: seeder `branches` berjalan sebelum akun Owner
        // ada (DB Design §9.2 urutan 1 mendahului urutan 3). ON DELETE
        // RESTRICT — C15, seluruh FK transaksi menolak penghapusan fisik
        // baris yang masih dirujuk.
        Blueprint::macro('userStamps', function (): Blueprint {
            /** @var Blueprint $this */
            $this->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $this->foreignUuid('updated_by')->nullable()->constrained('users')->restrictOnDelete();

            return $this;
        });

        // DB Design §1 / R12 — "branch_id uuid NOT NULL pada seluruh tabel
        // transaksi dan batch." Tidak dipakai tabel REPLICATED tanpa cabang
        // (mis. products, partners) — tabel tersebut memanggil macro ini
        // dengan sengaja dilewati, bukan diisi NULL.
        Blueprint::macro('branchId', function (string $column = 'branch_id'): Blueprint {
            /** @var Blueprint $this */
            $this->foreignUuid($column)->constrained('branches')->restrictOnDelete();

            return $this;
        });
    }
}
