<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DP/piutang parsial (T4.2) — D2 (migration kompatibel mundur, hanya
     * tambah kolom). `amount_paid`/`balance_due`/`payment_status` dikunci
     * `FinalizeSaleAction` saat finalisasi, sama pola dengan `subtotal`/
     * `total_amount` (T4.1) — nol/`unpaid` sepanjang draft.
     *
     * TIDAK ADA tabel `receivables` di sini — piutang penuh (jatuh tempo,
     * aging, pelunasan bertahap) adalah cakupan Fase 5 ("Kas + piutang",
     * §3). T4.2 hanya melacak SALDO pada dokumen penjualan itu sendiri —
     * cukup untuk PRD Fase 4 ("DP") tanpa membangun modul piutang penuh
     * lebih awal dari jadwalnya. Didokumentasikan sebagai batas cakupan,
     * bukan kelalaian — sama gaya dengan gap TH5a-c (`ProductPolicy`, T2.5).
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('amount_paid', 18, 2)->default(0)->after('total_amount');
            $table->decimal('balance_due', 18, 2)->default(0)->after('amount_paid');
            $table->string('payment_status', 20)->default('unpaid')->after('balance_due');
        });

        DB::statement(
            'ALTER TABLE sales ADD CONSTRAINT sales_payment_tracking_non_negative_check '.
            'CHECK (amount_paid >= 0 AND balance_due >= 0)'
        );
        DB::statement(
            'ALTER TABLE sales ADD CONSTRAINT sales_payment_status_check '.
            "CHECK (payment_status IN ('unpaid', 'partial', 'paid'))"
        );
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'balance_due', 'payment_status']);
        });
    }
};
