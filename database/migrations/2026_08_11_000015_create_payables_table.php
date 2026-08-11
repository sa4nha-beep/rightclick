<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sisi AP dari `receivables` (lihat docblock migration itu untuk alasan
     * desain lengkap — treatment simetris penuh). `purchase_invoice_id` FK
     * langsung menggantikan `sale_id`.
     *
     * BEDA dari `receivables`: dibuat SEKALI untuk SETIAP `PurchaseInvoice`
     * yang difinalisasi oleh `FinalizePurchaseInvoiceAction` (bukan hanya
     * yang bersaldo > 0) — karena `purchase_invoices` sebelumnya TIDAK
     * pernah punya kolom `amount_paid` yang terisi sebagian saat finalisasi
     * (beda dari `sales`, tidak ada konsep "DP" di sisi pembelian di T5.2),
     * jadi `total_amount` penuh SELALU jadi `original_amount` awal.
     */
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('purchase_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
            $table->foreignUuid('partner_id')->constrained('partners')->restrictOnDelete();
            $table->decimal('original_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2);
            $table->string('payment_status', 20)->default('unpaid');
            $table->date('due_date')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'partner_id']);
            $table->unique('purchase_invoice_id');
        });

        DB::statement(
            'ALTER TABLE payables ADD CONSTRAINT payables_amounts_non_negative_check '.
            'CHECK (original_amount >= 0 AND paid_amount >= 0 AND outstanding_amount >= 0)'
        );
        DB::statement(
            'ALTER TABLE payables ADD CONSTRAINT payables_paid_not_exceeding_original_check '.
            'CHECK (paid_amount <= original_amount)'
        );
        DB::statement(
            'ALTER TABLE payables ADD CONSTRAINT payables_outstanding_consistent_check '.
            'CHECK (outstanding_amount = original_amount - paid_amount)'
        );
        DB::statement(
            'ALTER TABLE payables ADD CONSTRAINT payables_payment_status_check '.
            "CHECK (payment_status IN ('unpaid', 'partial', 'paid'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payables');
    }
};
