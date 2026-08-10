<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger mutasi stok — T3.2, R1: "stock_mutations adalah satu-satunya
     * sumber kebenaran stok ... Hanya StockLedgerService yang boleh
     * menulis." Ditegakkan oleh `tests/Arch/StockMutationSingleWriterTest.php`.
     *
     * Append-only, sama persis bentuknya dengan `audit_logs` (T1.11 note di
     * model `AuditLog`): tidak ada soft delete, tidak ada `updated_at`.
     * Void TIDAK menghapus/mengubah baris lama — menerbitkan mutasi
     * berlawanan yang merujuk dokumen void (§16 peringatan #9).
     *
     * `quantity` bertanda: positif untuk penambahan stok (penerimaan,
     * transfer masuk, koreksi opname naik), negatif untuk pengurangan
     * (penjualan kelak, transfer keluar, koreksi opname turun).
     *
     * `reference_type`/`reference_id` WAJIB NOT NULL (R1) — setiap baris
     * mutasi harus bisa ditelusuri ke dokumen yang menerbitkannya.
     *
     * `serial_numbers` ditambahkan T3.7 (lihat migration terpisah) — tidak
     * di sini agar migration T3.2 tetap murni ledger inti.
     */
    public function up(): void
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('stock_batch_id')->constrained('stock_batches')->restrictOnDelete();
            $table->string('mutation_type', 40);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2);
            $table->string('reference_type');
            $table->uuid('reference_id');
            $table->timestampTz('occurred_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->index(['branch_id', 'product_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement(
            'ALTER TABLE stock_mutations ADD CONSTRAINT stock_mutations_quantity_not_zero_check '.
            'CHECK (quantity <> 0)'
        );
        DB::statement(
            'ALTER TABLE stock_mutations ADD CONSTRAINT stock_mutations_unit_cost_positive_check '.
            'CHECK (unit_cost > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
