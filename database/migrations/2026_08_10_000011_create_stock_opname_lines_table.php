<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris stock opname — T3.4. `system_qty` diisi ulang oleh
     * `FinalizeStockOpnameAction` tepat sebelum menghitung selisih (bukan
     * dipercaya dari nilai saat draft dibuat — mencegah TOCTOU bila stok
     * berubah antara draft dan finalisasi), lalu disimpan di sini sebagai
     * jejak nilai yang benar-benar dipakai.
     *
     * `reason` WAJIB bila `counted_qty <> system_qty` (AC-12) — ditegakkan
     * `FinalizeStockOpnameAction`, bukan CHECK constraint, karena
     * `system_qty` final baru diketahui saat itu juga.
     *
     * `unit_cost` WAJIB untuk `type=opening_balance` atau bila belum ada
     * batch sebelumnya bagi produk ini (tidak ada biaya untuk diwariskan).
     *
     * TANPA soft delete/`userStamps()` sendiri — baris hidup di dalam
     * siklus draft/final header (`stock_opnames`); begitu header final,
     * baris ikut terkunci lewat larangan edit dokumen final (R4), bukan
     * lewat mekanisme sendiri.
     */
    public function up(): void
    {
        Schema::create('stock_opname_lines', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('stock_opname_id')->constrained('stock_opnames')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('system_qty', 18, 4)->default(0);
            $table->decimal('counted_qty', 18, 4);
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->text('reason')->nullable();
            $table->timestampsTz();

            $table->unique(['stock_opname_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_lines');
    }
};
