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

        // R4 / DB Design §3 (`document_state`) / §4.x (mis. `sales.state`) —
        // kolom siklus hidup dokumen yang dipasangkan dengan trait
        // App\Infrastructure\Persistence\Concerns\HasDocumentState (T1.8).
        // Dipakai tabel dokumen transaksi mulai T3.5 (opname), T4.3 (sales),
        // T5.1 (purchase_orders), dst. — belum ada tabel nyata yang
        // memakainya pada T1.8 sendiri.
        Blueprint::macro('documentStateColumns', function (): Blueprint {
            /** @var Blueprint $this */
            $this->string('state', 20)->default('draft');
            $this->timestampTz('finalized_at')->nullable();
            $this->timestampTz('voided_at')->nullable();
            $this->foreignUuid('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $this->text('void_reason')->nullable();

            return $this;
        });
    }

    /**
     * SQL mentah untuk constraint C7 (DB Design §7): "`state = 'void'`
     * mensyaratkan `voided_at`, `voided_by`, `void_reason` terisi."
     *
     * Bukan macro Blueprint karena CHECK constraint lintas kolom dieksekusi
     * setelah tabel dibuat, bukan di dalam closure `Schema::create`. Panggil
     * lewat `DB::statement(...)` tepat setelah `Schema::create($table, ...)`
     * pada migration yang memakai `documentStateColumns()`.
     */
    public static function documentStateVoidCheckSql(string $table): string
    {
        return sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s_state_void_fields_check '.
            'CHECK (state <> \'void\' OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL))',
            $table,
            $table,
        );
    }
}
