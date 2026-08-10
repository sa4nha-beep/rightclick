<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger kas — T5.4, AC-21: "Kas keluar tanpa referensi dokumen →
     * penyimpanan ditolak." Append-only, sama bentuknya dengan
     * `stock_mutations`/`audit_logs` — tidak ada soft delete, tidak ada
     * `updated_at`.
     *
     * SATU-SATUNYA jalur tulis yang sah adalah
     * `App\Application\Services\CashLedgerService` (ditegakkan
     * `tests/Arch/CashEntrySingleWriterTest.php`, pola sama R1/T3.2).
     *
     * `amount` BERTANDA — positif untuk kas masuk (pembayaran penjualan
     * tunai), negatif untuk kas keluar (pembayaran hutang tunai) — sama
     * konvensi `stock_mutations.quantity`, bukan kolom `direction` terpisah.
     *
     * `reference_type`/`reference_id` WAJIB NOT NULL (AC-21) — SELALU
     * menunjuk DOKUMEN INDUK (`Sale`/`PurchaseInvoice`), bukan baris
     * pembayaran individual (`SalePayment`/`PurchasePayment`) — pola sama
     * `stock_mutations` yang menunjuk dokumen (mis. `StockAdjustment`),
     * bukan barisnya. Tidak ada jalur manual/bebas-referensi di MVP ini
     * sama sekali — `CashLedgerService::record()` mensyaratkan parameter
     * `Model $reference` non-nullable di level tipe PHP, dipertegas NOT
     * NULL di sini sebagai pertahanan berlapis (sama pola `StockLedgerService::receive()`).
     * Tidak ada modul "kas keluar bebas/petty cash" di MVP — akuntansi
     * penuh eksplisit di luar cakupan (CLAUDE.md §3).
     */
    public function up(): void
    {
        Schema::create('cash_entries', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->string('entry_type', 40);
            $table->decimal('amount', 18, 2);
            $table->string('reference_type');
            $table->uuid('reference_id');
            $table->timestampTz('occurred_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->index(['branch_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement(
            'ALTER TABLE cash_entries ADD CONSTRAINT cash_entries_amount_not_zero_check CHECK (amount <> 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_entries');
    }
};
