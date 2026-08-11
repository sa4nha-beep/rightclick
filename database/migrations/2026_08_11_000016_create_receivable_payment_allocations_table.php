<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penutup gap FR-M11a-05 (`HS-DB-RIGHTCLICK-v1.0` §4.6, tabel
     * `payment_allocations`): "satu setoran terhadap beberapa tagihan".
     * Implementasi lama (`receivable_payments.sale_id`) mengikat SATU
     * baris pembayaran ke TEPAT SATU `Sale` — kapabilitas alokasi banyak
     * tagihan sekaligus tidak ada.
     *
     * BEDA dari spek literal: spek menaut `payment_allocations.cash_entry_id`
     * (mengasumsikan `cash_entries` mencakup SEMUA metode pembayaran).
     * Keputusan sadar sesi ini: `cash_entries`/`CashLedgerService` TETAP
     * ledger tunai-saja append-only yang sudah teruji (AC-21, arch test
     * single-writer) — TIDAK diredesain jadi dokumen multi-metode (proyek
     * terpisah, jauh lebih besar). Sebagai gantinya, tabel ini menaut ke
     * `receivable_payments` (header peristiwa pembayaran — method/amount
     * total/reference_no, MENCAKUP seluruh metode, bukan cuma tunai) yang
     * SUDAH ADA sejak T5.5, bukan ke `cash_entries`.
     *
     * Pola child-line murni (sama `sale_items`/`stock_opname_lines`) — TANPA
     * soft delete, dibuat SEKALI di transaksi `RecordReceivablePaymentAction`,
     * tidak pernah diedit/dihapus individual (koreksi hanya lewat mekanisme
     * masa depan, sama batas cakupan `receivable_payments`/`purchase_payments`
     * sejak awal).
     */
    public function up(): void
    {
        Schema::create('receivable_payment_allocations', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('receivable_payment_id')->constrained('receivable_payments')->restrictOnDelete();
            $table->foreignUuid('receivable_id')->constrained('receivables')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestampsTz();

            $table->index('receivable_payment_id');
            $table->index('receivable_id');
        });

        DB::statement(
            'ALTER TABLE receivable_payment_allocations ADD CONSTRAINT receivable_payment_allocations_amount_positive_check '.
            'CHECK (amount > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payment_allocations');
    }
};
