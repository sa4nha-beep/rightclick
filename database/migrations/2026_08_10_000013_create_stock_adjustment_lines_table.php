<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris penyesuaian stok — T3.5. `reason` SELALU wajib (NOT NULL) —
     * berbeda dari `stock_opname_lines` yang hanya wajib bila berselisih:
     * penyesuaian TIDAK punya "hitung fisik" sebagai konteks, jadi setiap
     * baris perlu alasan eksplisit sejak awal (§2 — "tidak mengubah stok
     * tanpa alasan tercatat").
     *
     * `unit_cost` WAJIB untuk `direction=increase` (tidak ada nilai untuk
     * diwariskan dari mana pun — beda dari opname yang boleh mewarisi batch
     * terbaru). `direction=decrease` tidak butuh `unit_cost` — FIFO yang
     * menentukan biaya baris keluar.
     */
    public function up(): void
    {
        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('stock_adjustment_id')->constrained('stock_adjustments')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('direction', 10);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->text('reason');
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE stock_adjustment_lines ADD CONSTRAINT stock_adjustment_lines_quantity_positive_check '.
            'CHECK (quantity > 0)'
        );
        DB::statement(
            'ALTER TABLE stock_adjustment_lines ADD CONSTRAINT stock_adjustment_lines_direction_check '.
            "CHECK (direction IN ('increase', 'decrease'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');
    }
};
