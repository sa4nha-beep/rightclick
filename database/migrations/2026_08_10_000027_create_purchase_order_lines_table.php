<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris purchase order — T5.1. Hanya `product_id` (bukan `service_id`
     * seperti `sale_items`) — procurement memesan barang fisik, bukan jasa.
     *
     * `unit_price` adalah harga yang DINEGOSIASIKAN/DIPESAN — BUKAN
     * `unit_cost` batch (R2, termasuk PPN). Nilai batch sebenarnya baru
     * ditentukan saat faktur pembelian masuk (T5.2); kolom ini murni
     * catatan rencana pembelian, tidak pernah mengalir ke `stock_batches`.
     *
     * `line_total` diisi otomatis (`quantity * unit_price`) lewat model
     * event `saving`, sama pola dengan `SaleItem::line_total`.
     */
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_quantity_positive_check '.
            'CHECK (quantity > 0)'
        );
        DB::statement(
            'ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_unit_price_non_negative_check '.
            'CHECK (unit_price >= 0)'
        );
        DB::statement(
            'ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_line_total_non_negative_check '.
            'CHECK (line_total >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
