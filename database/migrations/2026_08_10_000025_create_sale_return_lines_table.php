<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris retur penjualan — T4.3, AC-18: "Barang diretur → kembali pada
     * nilai perolehan batch asal, bukan harga jual."
     *
     * `sale_item_id` (BUKAN `product_id` langsung) — retur SELALU merujuk
     * baris penjualan asal spesifik, sehingga `unit_cost`/`unit_price` bisa
     * disalin dari sana, bukan dihitung ulang dari harga master data yang
     * mungkin sudah berubah sejak penjualan terjadi.
     *
     * `unit_cost` DAN `unit_price` nullable — dikunci `FinalizeSaleReturnAction`
     * dari `sale_items.unit_cost_snapshot`/`unit_price` PADA SAAT finalisasi
     * (bukan disalin saat draft dibuat) — pola TOCTOU yang sama dipakai
     * `FinalizeStockOpnameAction`/`CloseCashierShiftAction`, walau di sini
     * nilai sumbernya (sale final) sudah tidak mungkin berubah lagi (R4) —
     * konsistensi pola tetap dipilih drpada pengecualian.
     *
     * DUA nilai berbeda dengan tujuan berbeda (inti AC-18):
     *   - `unit_cost` → nilai yang MASUK KEMBALI ke `stock_batches` via
     *     `StockLedgerService::receive()` (HPP, bukan harga jual).
     *   - `unit_price` × `quantity` = `refund_amount` → CATATAN nilai yang
     *     seharusnya dikembalikan ke pelanggan (harga jual asli baris itu).
     *   Menyamakan keduanya akan membuat HPP tercatat memakai harga jual —
     *   pelanggaran AC-18 yang eksplisit.
     *
     * `reason` SELALU wajib (sama pola `stock_adjustment_lines` — retur
     * tidak punya "hitung fisik" sebagai konteks otomatis).
     *
     * `serial_numbers` (R3, T3.7) DIVALIDASI wajib bila produk serial —
     * retur adalah sisi "naik" (barang masuk kembali ke stok), berbeda dari
     * `sale_items` (sisi konsumsi, tidak wajib serial).
     */
    public function up(): void
    {
        Schema::create('sale_return_lines', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('sale_return_id')->constrained('sale_returns')->restrictOnDelete();
            $table->foreignUuid('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('refund_amount', 18, 2)->nullable();
            $table->text('reason');
            $table->jsonb('serial_numbers')->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE sale_return_lines ADD CONSTRAINT sale_return_lines_quantity_positive_check '.
            'CHECK (quantity > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_lines');
    }
};
