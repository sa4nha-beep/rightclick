<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris penjualan — T4.1. Tepat satu dari `product_id`/`service_id`
     * wajib terisi (CHECK constraint) — belum ada abstraksi "sellable"
     * bersama di Fase 2/3 (`Product` dan `Service` adalah model terpisah
     * tanpa induk umum), jadi dua FK nullable + CHECK exclusive-or dipilih
     * alih-alih morph polymorphic agar FK database tetap menegakkan
     * integritas referensial (morph tidak punya FK asli).
     *
     * `unit_price` adalah SALINAN harga jual saat baris dibuat (bukan live
     * reference ke `products.selling_price`) — harga di master data bisa
     * berubah setelah baris ditulis, nota harus mencerminkan harga saat
     * transaksi terjadi.
     *
     * `unit_cost_snapshot` NULL sampai `FinalizeSaleAction` mengisi dari
     * HASIL NYATA FIFO `StockLedgerService::consume()` (bukan estimasi rata-
     * rata seperti `FinalizeStockAdjustmentAction` — untuk penjualan COGS
     * presisi tersedia langsung dari `Collection<StockConsumption>` yang
     * dikembalikan `consume()`). NULL permanen untuk baris jasa (`service_id`
     * terisi) — jasa tidak punya HPP, bukan nilai yang belum diisi.
     *
     * `serial_numbers` (R3) — saat migration ini dibuat (T4.1), T3.7
     * menetapkan validasi serial hanya pada sisi "naik"/perpindahan dan
     * sengaja membiarkan sisi konsumsi (penjualan) tanpa validasi wajib.
     * **Ditutup kemudian** (penutupan gap T4.9/UT5 terhadap
     * `HS-TASKS-RIGHTCLICK-v1.1`): `FinalizeSaleAction::validateStructure()`
     * kini memanggil `SerialNumberValidationService` yang sama untuk setiap
     * baris `product_id` — produk `is_serialized` WAJIB mengisi serial number
     * sejumlah `quantity` sebelum pembayaran dapat difinalisasi.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignUuid('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('unit_cost_snapshot', 18, 2)->nullable();
            $table->decimal('line_total', 18, 2);
            $table->jsonb('serial_numbers')->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE sale_items ADD CONSTRAINT sale_items_exactly_one_sellable_check '.
            'CHECK ((product_id IS NOT NULL AND service_id IS NULL) OR (product_id IS NULL AND service_id IS NOT NULL))'
        );
        DB::statement(
            'ALTER TABLE sale_items ADD CONSTRAINT sale_items_quantity_positive_check CHECK (quantity > 0)'
        );
        DB::statement(
            'ALTER TABLE sale_items ADD CONSTRAINT sale_items_unit_price_non_negative_check CHECK (unit_price >= 0)'
        );
        DB::statement(
            'ALTER TABLE sale_items ADD CONSTRAINT sale_items_line_total_non_negative_check CHECK (line_total >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
