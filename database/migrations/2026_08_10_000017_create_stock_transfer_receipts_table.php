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
     * Transfer stok antar cabang — dokumen TERIMA (T3.6, R12). Baris
     * terpisah dari `stock_transfers` (dokumen kirim) — lihat catatan di
     * sana. `branch_id` di sini adalah cabang TUJUAN/penerima (bukan cabang
     * asal `stock_transfers.branch_id`).
     *
     * `unique(stock_transfer_id)` — MVP hanya menerima transfer secara utuh
     * sekali jalan, tidak ada penerimaan sebagian/bertahap (short receipt),
     * disengaja dibatasi untuk MVP.
     *
     * `document_number` prefix `TRI`, self-derived (lihat catatan
     * `DocumentType`), terisi saat `ReceiveStockTransferAction` finalisasi.
     */
    public function up(): void
    {
        Schema::create('stock_transfer_receipts', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId(); // cabang tujuan/penerima
            $table->foreignUuid('stock_transfer_id')->unique()->constrained('stock_transfers')->restrictOnDelete();
            $table->string('document_number', 60)->nullable();
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'state']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('stock_transfer_receipts'));

        DB::statement(
            'CREATE UNIQUE INDEX stock_transfer_receipts_document_number_unique ON stock_transfer_receipts (document_number) '.
            'WHERE document_number IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_receipts');
    }
};
