<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penutup gap terhadap `HS-DB-RIGHTCLICK-v1.0` §4.6 (Final, disetujui
     * COO): spesifikasi mensyaratkan tabel `receivables` dengan saldo
     * TERSIMPAN (`original_amount`/`paid_amount`/`outstanding_amount`/
     * `payment_status`) — sebelumnya (T5.5 self-derived) saldo piutang
     * HANYA dihitung on-the-fly dari `SUM(receivable_payments.amount)`
     * tanpa tabel dedicated sama sekali.
     *
     * `sale_id` FK LANGSUNG (BUKAN `source_type`/`source_id` polimorfik
     * seperti spek) — MVP hanya punya SATU jenis sumber piutang (`Sale`),
     * polimorfisme tidak menambah kapabilitas apa pun saat ini dan FK
     * langsung menjaga integritas referensial database (morph tidak punya
     * FK asli, pola sama alasan `sale_items` memakai dua FK nullable+CHECK
     * alih-alih morph, lihat docblock migration itu). Mudah dipromosikan
     * ke polimorfik nanti bila jenis sumber piutang kedua muncul.
     *
     * `partner_id` didenormalisasi dari `sales.partner_id` — memungkinkan
     * `outstandingReceivableForPartner()` jadi SATU query `SUM` langsung
     * (bukan loop N+1 per Sale seperti implementasi lama).
     *
     * Dibuat SEKALI oleh `FinalizeSaleAction` saat `balance_due > 0`
     * (DP/piutang parsial) — TIDAK ADA baris untuk Sale yang lunas penuh
     * saat finalisasi. Baris ini BUKAN dokumen berstatus draft/final/void
     * (tanpa `documentStateColumns()`) — murni cache turunan tersinkron,
     * diperbarui `RecordReceivablePaymentAction` setiap kali alokasi
     * pembayaran baru dicatat. `due_date` NULL — `partners.payment_term_days`
     * dari `HS-DB-RIGHTCLICK-v1.0` §4.2 tidak ada di skema aktual, jadi
     * tidak bisa dihitung otomatis; dicatat sebagai batas cakupan, bukan
     * kelalaian.
     *
     * Soft delete (R5, SYNCED) — dihapus (soft) oleh `VoidSaleAction` saat
     * Sale induknya dibatalkan (hanya diizinkan selama `paid_amount = 0`).
     */
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignUuid('partner_id')->constrained('partners')->restrictOnDelete();
            $table->decimal('original_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2);
            $table->string('payment_status', 20)->default('unpaid');
            $table->date('due_date')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'partner_id']);
            $table->unique('sale_id');
        });

        DB::statement(
            'ALTER TABLE receivables ADD CONSTRAINT receivables_amounts_non_negative_check '.
            'CHECK (original_amount >= 0 AND paid_amount >= 0 AND outstanding_amount >= 0)'
        );
        DB::statement(
            'ALTER TABLE receivables ADD CONSTRAINT receivables_paid_not_exceeding_original_check '.
            'CHECK (paid_amount <= original_amount)'
        );
        DB::statement(
            'ALTER TABLE receivables ADD CONSTRAINT receivables_outstanding_consistent_check '.
            'CHECK (outstanding_amount = original_amount - paid_amount)'
        );
        DB::statement(
            'ALTER TABLE receivables ADD CONSTRAINT receivables_payment_status_check '.
            "CHECK (payment_status IN ('unpaid', 'partial', 'paid'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
