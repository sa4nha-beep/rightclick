<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rincian batch sumber yang terpakai FIFO saat dispatch — T3.6.
     * Ditulis oleh `DispatchStockTransferAction` dari
     * `StockLedgerService::consume()` (nilai kembalian `StockConsumption`)
     * PERSIS pada saat dispatch difinalisasi.
     *
     * Alasan tabel ini ada: `ReceiveStockTransferAction` bisa berjalan
     * kapan pun setelahnya (bahkan hari lain) — biaya batch sumber pada
     * saat dispatch harus diwariskan APA ADANYA ke batch baru di cabang
     * tujuan, terlepas dari apa yang terjadi pada batch sumber di antara
     * dispatch dan penerimaan. Tanpa rekaman ini, biaya asal hilang dan
     * `ReceiveStockTransferAction` tidak lagi tahu unit_cost yang benar
     * (R2 — HPP harus akurat per batch).
     */
    public function up(): void
    {
        Schema::create('stock_transfer_line_batches', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('stock_transfer_line_id')->constrained('stock_transfer_lines')->restrictOnDelete();
            $table->foreignUuid('source_stock_batch_id')->constrained('stock_batches')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2);
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE stock_transfer_line_batches ADD CONSTRAINT stock_transfer_line_batches_quantity_positive_check '.
            'CHECK (quantity > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_line_batches');
    }
};
