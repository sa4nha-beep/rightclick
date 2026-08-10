<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris pembayaran penjualan (multi-payment) — T4.1. `method` self-
     * derived (`App\Domain\Sales\Enums\PaymentMethod`) — tidak ada daftar
     * metode pembayaran terdokumentasi di CLAUDE.md, diturunkan dari
     * kebutuhan retail umum (tunai/kartu/transfer/QRIS/lainnya).
     *
     * Jumlah `sale_payments.amount` per `sale_id` harus persis sama dengan
     * `sales.total_amount` saat finalisasi (T4.1 — pembayaran LUNAS saja;
     * DP/piutang parsial ditunda ke T4.2) — ditegakkan `FinalizeSaleAction`,
     * bukan CHECK constraint (perbandingan antar tabel).
     */
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 18, 2);
            $table->string('reference_no', 100)->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            'ALTER TABLE sale_payments ADD CONSTRAINT sale_payments_amount_positive_check CHECK (amount > 0)'
        );
        DB::statement(
            'ALTER TABLE sale_payments ADD CONSTRAINT sale_payments_method_check '.
            "CHECK (method IN ('cash', 'card', 'transfer', 'qris', 'other'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
