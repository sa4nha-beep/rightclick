<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris penerimaan barang — T5.2, simpul kritis (R2/AC-09). `unit_cost`
     * di sini adalah nilai yang SEBENARNYA mengalir ke `stock_batches`
     * lewat `StockLedgerService::receive()` — WAJIB TERMASUK PPN, diisi
     * dari nilai faktur pemasok apa adanya (HAEN KOMPUTER non-PKP, R2:
     * "PPN tidak dapat dikreditkan"). BEDA dari `purchase_order_lines.unit_price`
     * (T5.1) yang murni harga pesanan/rencana, tidak pernah menyentuh
     * ledger.
     *
     * `serial_numbers` (R3/T3.7) — penerimaan adalah sisi "naik", divalidasi
     * wajib untuk produk serial, sama pola `stock_adjustment_lines`/
     * `stock_transfer_lines`.
     *
     * `line_total` diisi otomatis (`quantity * unit_cost`) lewat model event
     * `saving`, sama pola `SaleItem`/`PurchaseOrderLine`.
     */
    public function up(): void
    {
        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->jsonb('serial_numbers')->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE goods_receipt_lines ADD CONSTRAINT goods_receipt_lines_quantity_positive_check '.
            'CHECK (quantity > 0)'
        );
        DB::statement(
            'ALTER TABLE goods_receipt_lines ADD CONSTRAINT goods_receipt_lines_unit_cost_non_negative_check '.
            'CHECK (unit_cost >= 0)'
        );
        DB::statement(
            'ALTER TABLE goods_receipt_lines ADD CONSTRAINT goods_receipt_lines_line_total_non_negative_check '.
            'CHECK (line_total >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
