<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penutup gap pada AC-16 asli (`HS-TASKS-RIGHTCLICK-v1.1` T4.2: "Buka &
     * tutup shift dengan hitung PER PECAHAN") dan skema
     * `HS-DB-RIGHTCLICK-v1.0` §4.4 — sebelumnya `CloseCashierShiftAction`
     * hanya menerima satu angka agregat `closing_cash_counted`, tanpa
     * rincian per pecahan uang.
     *
     * Baris anak murni (pola sama `sale_items`/`stock_opname_lines`) —
     * TANPA soft delete, tanpa `updated_at` yang berarti (dibuat SEKALI di
     * dalam transaksi yang sama dengan `CloseCashierShiftAction::execute()`,
     * SEBELUM shift ditransisikan ke `final`, jadi tidak pernah menyentuh
     * guard `HasDocumentState` — lihat docblock action).
     *
     * `subtotal` disimpan (bukan dihitung ulang di aplikasi setiap saat)
     * murni supaya rekonsiliasi/laporan tidak perlu mengalikan ulang setiap
     * baca — `denomination * quantity` tetap harus sama persis, ditegakkan
     * CHECK constraint agar tidak ada baris yang diam-diam tidak konsisten.
     */
    public function up(): void
    {
        Schema::create('cashier_shift_counts', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('cashier_shift_id')->constrained('cashier_shifts')->cascadeOnDelete();
            $table->decimal('denomination', 18, 2);
            $table->integer('quantity');
            $table->decimal('subtotal', 18, 2);
            $table->timestampsTz();

            $table->index('cashier_shift_id');
        });

        DB::statement(
            'ALTER TABLE cashier_shift_counts ADD CONSTRAINT cashier_shift_counts_denomination_positive_check '.
            'CHECK (denomination > 0)'
        );
        DB::statement(
            'ALTER TABLE cashier_shift_counts ADD CONSTRAINT cashier_shift_counts_quantity_non_negative_check '.
            'CHECK (quantity >= 0)'
        );
        DB::statement(
            'ALTER TABLE cashier_shift_counts ADD CONSTRAINT cashier_shift_counts_subtotal_check '.
            'CHECK (subtotal = denomination * quantity)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shift_counts');
    }
};
