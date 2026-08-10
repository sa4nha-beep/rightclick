<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris transfer — T3.6. Sama pola dengan `stock_opname_lines`/
     * `stock_adjustment_lines`: tanpa soft delete/`userStamps()` sendiri,
     * hidup di dalam siklus draft/final dokumen `stock_transfers` induk.
     */
    public function up(): void
    {
        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE stock_transfer_lines ADD CONSTRAINT stock_transfer_lines_quantity_positive_check '.
            'CHECK (quantity > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
    }
};
