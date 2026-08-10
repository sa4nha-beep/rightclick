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
     * Penyesuaian stok — T3.5. Draft → final → void (R4). `document_number`
     * (prefix `ADJ`, self-derived — lihat catatan `DocumentType`) hanya
     * terisi saat benar-benar diterapkan ke ledger (bukan saat baru
     * mengajukan approval — lihat `FinalizeStockAdjustmentAction`).
     *
     * TH3/TH3b (CLAUDE.md §10) ditegakkan di `FinalizeStockAdjustmentAction`,
     * bukan di sini — nilai ambang dibaca dari `settings` saat itu juga.
     */
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->string('document_number', 60)->nullable();
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'state']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('stock_adjustments'));

        DB::statement(
            'CREATE UNIQUE INDEX stock_adjustments_document_number_unique ON stock_adjustments (document_number) '.
            'WHERE document_number IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
