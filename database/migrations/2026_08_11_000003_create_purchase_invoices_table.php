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
     * Faktur pembelian — T5.2. Draft → final → void (R4). SYNCED (CLAUDE.md
     * §7). Catatan HUTANG/AP FORMAL, BUKAN pemicu ledger kedua — stok sudah
     * bergerak lewat `goods_receipts` (migration sebelumnya). Dokumen ini
     * murni menautkan nomor/tanggal faktur resmi pemasok ke penerimaan yang
     * sudah difinalisasi, menjadi dasar hutang yang dibaca T5.3.
     *
     * `goods_receipt_id` UNIK — 1:1 dengan `goods_receipts` (MVP tanpa
     * penerimaan sebagian/beberapa faktur per penerimaan — pola sama
     * `stock_transfer_receipts.stock_transfer_id` unik, T3.6).
     *
     * `partner_id` disalin dari `goods_receipts.partner_id` saat dibuat
     * (bukan live join) — kemudahan query langsung, sama pola kolom
     * `branch_id` pada tabel transaksi lain yang secara teknis bisa
     * diturunkan dari relasi tapi tetap disimpan eksplisit.
     *
     * `invoice_number`/`invoice_date` adalah nomor/tanggal PADA FAKTUR
     * FISIK PEMASOK — BEDA dari `document_number` (nomor internal
     * RIGHTCLICK, prefix `INV`, self-derived). Dua identitas berbeda untuk
     * dokumen yang sama, seperti halnya `sales.document_number` (nomor nota
     * kita) tidak pernah disamakan dengan nomor apa pun milik pelanggan.
     *
     * `total_amount` DIKUNCI dari `goods_receipts.total_amount` saat
     * `FinalizePurchaseInvoiceAction` (bukan dihitung ulang dari baris —
     * dokumen ini sengaja TANPA baris sendiri, lihat docblock
     * `FinalizePurchaseInvoiceAction`) — HARUS sama dengan nilai yang sudah
     * masuk ledger, mencegah faktur mengklaim nilai berbeda dari yang
     * benar-benar diterima.
     */
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignUuid('partner_id')->constrained('partners')->restrictOnDelete();
            $table->string('invoice_number', 60);
            $table->date('invoice_date');
            $table->string('document_number', 60)->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique('goods_receipt_id');
            $table->index(['branch_id', 'state']);
            $table->index(['partner_id']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('purchase_invoices'));

        DB::statement(
            'CREATE UNIQUE INDEX purchase_invoices_document_number_unique ON purchase_invoices (document_number) '.
            'WHERE document_number IS NOT NULL'
        );

        DB::statement(
            'ALTER TABLE purchase_invoices ADD CONSTRAINT purchase_invoices_total_amount_non_negative_check '.
            'CHECK (total_amount >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
