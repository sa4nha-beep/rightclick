<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris pembayaran hutang (T5.3) — cicilan/parsial atas
     * `purchase_invoices`. BEDA MENDASAR dari `sale_payments` (T4.1): baris
     * itu hanya ditulis SEKALI, atomik, sebagai bagian dari
     * `FinalizeSaleAction` (pembayaran lunas di muka). Di sini, satu faktur
     * BOLEH menerima banyak `purchase_payments` DARI WAKTU KE WAKTU setelah
     * faktur sudah final — itulah makna "cicilan" pada hutang dagang.
     *
     * Konsekuensi desain: `purchase_invoices` SENGAJA TIDAK punya kolom
     * `amount_paid`/`balance_due`/`payment_status` tersimpan (beda dari
     * `sales`, T4.2) — kolom semacam itu tidak bisa diperbarui pada dokumen
     * berstatus `final` (R4, `HasDocumentState` menolak perubahan field
     * apa pun selain transisi void). Saldo hutang DIHITUNG dari SUM
     * `purchase_payments.amount` per faktur vs `purchase_invoices.total_amount`
     * — pola sama `stock_balances` sebagai cache turunan dari
     * `stock_mutations` (R1), diterapkan ke konteks finansial. Lihat
     * `PurchaseInvoice::amountPaid()`/`balanceDue()`/`paymentStatus()`.
     *
     * `method` self-derived, memakai NILAI YANG SAMA dengan
     * `App\Domain\Sales\Enums\PaymentMethod` (dipakai ulang langsung, bukan
     * diduplikasi — lihat docblock `RecordPurchasePaymentAction` untuk
     * catatan bahwa enum ini idealnya dipromosikan ke `Domain\Shared`).
     *
     * TANPA soft delete/`userStamps()` — sama pola `sale_payments`: baris
     * anak yang immutable, tanpa mekanisme koreksi/pembatalan individual di
     * T5.3 (batas cakupan, bukan kelalaian — koreksi pembayaran salah input
     * adalah kerja masa depan).
     */
    public function up(): void
    {
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('purchase_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 18, 2);
            $table->string('reference_no', 100)->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE purchase_payments ADD CONSTRAINT purchase_payments_amount_positive_check CHECK (amount > 0)'
        );
        DB::statement(
            'ALTER TABLE purchase_payments ADD CONSTRAINT purchase_payments_method_check '.
            "CHECK (method IN ('cash', 'card', 'transfer', 'qris', 'other'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};
