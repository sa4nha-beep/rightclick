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
     * Retur penjualan — T4.3, AC-18. Draft → final → void (R4).
     * `document_number` (prefix `RET`, self-derived) hanya terisi saat
     * benar-benar diterapkan ke ledger (`FinalizeSaleReturnAction`), sama
     * pola dengan `stock_adjustments`/`sales`.
     *
     * `sale_id` WAJIB — setiap retur merujuk SATU penjualan FINAL yang
     * sudah ada (tidak ada retur "lepas" tanpa transaksi asal).
     *
     * SENGAJA branch-scoped SAMA dengan `sale.branch_id` (ditegakkan
     * `FinalizeSaleReturnAction`, bukan CHECK constraint lintas tabel) —
     * retur lintas cabang (barang dijual di Cabang A, diretur di Cabang B)
     * di luar cakupan T4.3; kasus itu perlu transfer stok terpisah setelah
     * retur diproses di cabang asal.
     *
     * `total_refund` dikunci saat finalisasi (jumlah `sale_return_lines.
     * refund_amount`, dihitung dari `unit_price` ASLI baris penjualan —
     * BUKAN nilai stok yang dikembalikan, lihat catatan `sale_return_lines`
     * untuk AC-18). Ini murni CATATAN nilai yang seharusnya dikembalikan ke
     * pelanggan — TIDAK ADA pencatatan kas keluar sungguhan di sini
     * (`cash_entries` adalah Fase 5), sama batas cakupan dengan DP T4.2.
     */
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->string('document_number', 60)->nullable();
            $table->decimal('total_refund', 18, 2)->default(0);
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'state']);
            $table->index(['sale_id']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('sale_returns'));

        DB::statement(
            'CREATE UNIQUE INDEX sale_returns_document_number_unique ON sale_returns (document_number) '.
            'WHERE document_number IS NOT NULL'
        );

        DB::statement(
            'ALTER TABLE sale_returns ADD CONSTRAINT sale_returns_total_refund_non_negative_check '.
            'CHECK (total_refund >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
