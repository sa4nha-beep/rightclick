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
     * Penerimaan barang — T5.2, simpul kritis. Draft → final → void (R4).
     * SYNCED (CLAUDE.md §7).
     *
     * `purchase_order_id` NULLABLE (BUKAN kelalaian) — Gudang (pemegang
     * `perform_goods_receipt`) TIDAK memiliki `view_purchase_orders`
     * (PermissionSeeder), jadi mensyaratkan tautan wajib ke PO tertentu
     * akan memaksa alur yang permission-nya sendiri tidak mendukung.
     * Penerimaan ad-hoc (barang datang tanpa PO formal — realistis untuk
     * toko retail kecil, §1) tetap valid; tautan PO murni untuk
     * ketertelusuran opsional.
     *
     * `partner_id` WAJIB langsung di sini (bukan hanya lewat
     * `purchase_order_id`) — Gudang punya `view_partners`, mengenali
     * pemasok dari surat jalan/faktur yang menyertai barang.
     *
     * `total_amount` dijumlahkan dari `goods_receipt_lines.line_total` saat
     * `FinalizeGoodsReceiptAction` — sama pola `purchase_orders.total_amount`
     * (T5.1)/`sales.total_amount` (T4.1).
     *
     * TIDAK ADA kolom ambang/approval — CLAUDE.md §10 tidak menetapkan TH
     * untuk goods receipt (beda dari PO/TH4, adjustment/TH3). Finalisasi
     * murni digerbang permission (`perform_goods_receipt`), tanpa alur
     * `ApprovalService` — lihat `FinalizeGoodsReceiptAction`.
     *
     * Inilah dokumen yang MEMANGGIL `StockLedgerService::receive()`
     * (bukan `purchase_invoices`) — unit_cost per baris (`goods_receipt_lines`)
     * diisi TERMASUK PPN (R2/AC-09) langsung dari nilai faktur pemasok yang
     * menyertai barang secara fisik; HAEN KOMPUTER non-PKP (R2) sehingga
     * tidak ada pemisahan PPN masukan yang perlu dikreditkan — nilai faktur
     * pemasok DIKETIK APA ADANYA. `purchase_invoices` (tabel terpisah di
     * migration berikutnya) adalah catatan HUTANG/AP formal yang menaut
     * balik ke sini SETELAH stok sudah bergerak — bukan pemicu ledger kedua.
     */
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignUuid('partner_id')->constrained('partners')->restrictOnDelete();
            $table->string('document_number', 60)->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'state']);
            $table->index(['partner_id']);
            $table->index(['purchase_order_id']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('goods_receipts'));

        DB::statement(
            'CREATE UNIQUE INDEX goods_receipts_document_number_unique ON goods_receipts (document_number) '.
            'WHERE document_number IS NOT NULL'
        );

        DB::statement(
            'ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_total_amount_non_negative_check '.
            'CHECK (total_amount >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
