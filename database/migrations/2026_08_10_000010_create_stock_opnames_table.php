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
     * Stock opname — T3.4. Dokumen draft → final → void (R4) via
     * `HasDocumentState`/`documentStateColumns()`. `document_number`
     * (prefix `OPN`, self-derived — lihat catatan `DocumentType`) hanya
     * terisi saat finalisasi (`FinalizeStockOpnameAction`), bukan saat draft
     * dibuat — mencegah nomor bocor dari draft yang dibuang.
     *
     * `type=opening_balance` (R9) mensyaratkan permission
     * `adjust_opening_balance` — lihat `StockOpnamePolicy::finalize()`.
     */
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->string('type', 20)->default('periodic');
            $table->string('document_number', 60)->nullable();
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'state']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('stock_opnames'));

        DB::statement(
            'CREATE UNIQUE INDEX stock_opnames_document_number_unique ON stock_opnames (document_number) '.
            'WHERE document_number IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
