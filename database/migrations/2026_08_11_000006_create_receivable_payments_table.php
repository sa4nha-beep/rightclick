<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris pelunasan piutang (T5.5) — cicilan/parsial atas sisa
     * `sales.balance_due` (DP, T4.2). Pola PERSIS `purchase_payments`
     * (T5.3), sisi kebalikannya (AR, bukan AP).
     *
     * `sales.balance_due` (dikunci `FinalizeSaleAction` saat sale itu
     * sendiri difinalisasi) TETAP APA ADANYA — merepresentasikan piutang
     * AWAL saat transaksi terjadi, TIDAK PERNAH diperbarui langsung
     * (R4/`HasDocumentState` menolak perubahan field pada dokumen final).
     * Sisa piutang KINI dihitung dari `sales.balance_due` DIKURANGI SUM
     * `receivable_payments.amount` — pola sama `PurchaseInvoice::balanceDue()`
     * (T5.3) diterapkan ke sisi AR. Lihat `Sale::amountCollected()`/
     * `remainingReceivable()`/`receivableStatus()`.
     *
     * `method` self-derived, nilai sama dengan `App\Domain\Sales\Enums\PaymentMethod`
     * (dipakai ulang, sama seperti `purchase_payments` — lihat catatan
     * `RecordReceivablePaymentAction` untuk kandidat promosi ke
     * `Domain\Shared`).
     *
     * TANPA soft delete/`userStamps()` — immutable, tanpa mekanisme koreksi
     * individual, sama batas cakupan `purchase_payments`.
     */
    public function up(): void
    {
        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 18, 2);
            $table->string('reference_no', 100)->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE receivable_payments ADD CONSTRAINT receivable_payments_amount_positive_check CHECK (amount > 0)'
        );
        DB::statement(
            'ALTER TABLE receivable_payments ADD CONSTRAINT receivable_payments_method_check '.
            "CHECK (method IN ('cash', 'card', 'transfer', 'qris', 'other'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
    }
};
