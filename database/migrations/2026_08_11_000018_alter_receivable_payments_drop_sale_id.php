<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bagian dari rebuild piutang/hutang (`HS-DB-RIGHTCLICK-v1.0` §4.6).
     * `sale_id` DIHAPUS — `receivable_payments` jadi murni header peristiwa
     * pembayaran (`method`/`amount` TOTAL/`reference_no`), sumber kebenaran
     * alokasi sepenuhnya berpindah ke `receivable_payment_allocations`
     * (satu header bisa punya banyak alokasi ke banyak `receivables`).
     * Menyimpan `sale_id` di sini SEKALIGUS alokasi di tabel terpisah akan
     * menciptakan dua sumber kebenaran yang bisa saling bertentangan.
     *
     * Dijalankan SETELAH tabel alokasi ada (migration sebelumnya) — tidak
     * ada window waktu tanpa mekanisme pencatatan alokasi.
     */
    public function up(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropColumn('sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table) {
            $table->foreignUuid('sale_id')->nullable()->constrained('sales')->restrictOnDelete();
        });
    }
};
