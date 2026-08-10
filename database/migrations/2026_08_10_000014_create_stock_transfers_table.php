<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Support\MigrationMacros;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transfer stok antar cabang — dokumen KIRIM (T3.6, R12). "Dua dokumen
     * (kirim + terima)" (CLAUDE.md §11) berarti dua BARIS/tabel terpisah,
     * bukan satu baris dengan dua nomor — lihat `stock_transfer_receipts`
     * untuk dokumen terima.
     *
     * `branch_id` (via `branchId()`/`BelongsToBranch`, seperti tabel lain)
     * adalah cabang ASAL/pengirim — bukan `source_branch_id` terpisah.
     * `dest_branch_id` adalah referensi biasa (BUKAN scope BranchScope
     * model ini), cabang tujuan yang nanti membuat
     * `stock_transfer_receipts` miliknya sendiri (branch_id = tujuan di
     * sana). Desain ini sengaja menghindari model dual-branch-scope custom
     * — setiap tabel tetap satu `branch_id` tunggal seperti pola yang sudah
     * ada di seluruh skema.
     *
     * `document_number` (prefix `TRO`, self-derived) hanya terisi saat
     * dispatch difinalisasi (`DispatchStockTransferAction`) — finalisasi
     * itulah yang mengonsumsi stok cabang asal (R1/R7), status barang
     * "transit" sampai `stock_transfer_receipts` terkait dibuat (AC-11).
     */
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId(); // cabang asal/pengirim
            $table->foreignUuid('dest_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('document_number', 60)->nullable();
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'state']);
            $table->index(['dest_branch_id', 'state']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('stock_transfers'));

        DB::statement(
            'ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_dest_branch_different_check '.
            'CHECK (dest_branch_id <> branch_id)'
        );

        DB::statement(
            'CREATE UNIQUE INDEX stock_transfers_document_number_unique ON stock_transfers (document_number) '.
            'WHERE document_number IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
