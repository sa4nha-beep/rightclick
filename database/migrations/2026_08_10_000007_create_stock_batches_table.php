<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batch stok — T3.1. SYNCED (CLAUDE.md §7): ditulis di cabang,
     * disinkronkan ke HQ lewat outbox (T5.7, belum ada di Fase 3).
     *
     * Satu-satunya jalur tulis adalah `App\Application\Services\StockLedgerService`
     * (T3.2, R1) — tidak ada halaman create/edit di Filament untuk tabel ini
     * (lihat `StockBatchPolicy`), batch selalu lahir dari dokumen (opname,
     * adjustment, penerimaan transfer, kelak goods receipt T5.2).
     *
     * `unit_cost` TERMASUK PPN (R2, §16 peringatan #1) — HAEN KOMPUTER
     * non-PKP, PPN tidak dapat dikreditkan. Jangan pernah mengisi kolom ini
     * dari nilai DPP faktur.
     *
     * `reference_type`/`reference_id` menunjuk dokumen yang menerbitkan batch
     * ini (StockOpname, StockAdjustment, StockTransferReceipt, kelak
     * GoodsReceipt) — jejak asal-usul HPP, terpisah dari
     * `stock_mutations.reference_type/reference_id` yang WAJIB per baris
     * mutasi (R1).
     *
     * Index `(branch_id, product_id, qty_remaining, received_at)` adalah
     * bentuk persis kueri FIFO wajib (CLAUDE.md §7): `WHERE branch_id = ?
     * AND product_id = ? AND qty_remaining > 0 ORDER BY received_at ASC
     * FOR UPDATE`.
     */
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->timestampTz('received_at');
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('qty_received', 18, 4);
            $table->decimal('qty_remaining', 18, 4);
            $table->string('reference_type');
            $table->uuid('reference_id');
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'product_id', 'qty_remaining', 'received_at'], 'stock_batches_fifo_index');
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement(
            'ALTER TABLE stock_batches ADD CONSTRAINT stock_batches_unit_cost_positive_check '.
            'CHECK (unit_cost > 0)'
        );
        DB::statement(
            'ALTER TABLE stock_batches ADD CONSTRAINT stock_batches_qty_received_positive_check '.
            'CHECK (qty_received > 0)'
        );
        DB::statement(
            'ALTER TABLE stock_batches ADD CONSTRAINT stock_batches_qty_remaining_range_check '.
            'CHECK (qty_remaining >= 0 AND qty_remaining <= qty_received)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
