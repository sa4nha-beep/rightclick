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
     * Purchase order — T5.1. Draft → final → void (R4). SYNCED (CLAUDE.md
     * §7) — ditulis di cabang, dikonsolidasi ke HQ lewat outbox (T5.7,
     * belum ada di sini).
     *
     * `partner_id` WAJIB — PO selalu ditujukan ke satu pemasok
     * (`PartnerType::Supplier`/`Both`, tidak ditegakkan di database karena
     * `partner_type` bisa berubah setelah PO dibuat; validasi ada di
     * `FinalizePurchaseOrderAction`).
     *
     * `total_amount` TIDAK dihitung ulang otomatis dari baris saat draft
     * diedit — nilainya nol sampai `FinalizePurchaseOrderAction` menjumlahkan
     * `purchase_order_lines.line_total` dan mengunci totalnya, sama pola
     * dengan `sales.total_amount` (T4.1).
     *
     * TH4 (CLAUDE.md §10, `po.max_admin` di `settings`, T1.9): PO di atas
     * Rp10.000.000 tanpa approval Owner ditegakkan di
     * `FinalizePurchaseOrderAction`, bukan di sini — nilai baru diketahui
     * setelah baris dibaca.
     *
     * Belum ada kolom biaya perolehan di sini — `unit_cost` batch yang
     * SEBENARNYA (termasuk PPN, R2) baru ditentukan saat faktur pembelian
     * masuk (T5.2), bukan dari PO. `purchase_order_lines.unit_price` adalah
     * estimasi/negosiasi, bukan nilai yang mengalir ke `stock_batches`.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('partner_id')->constrained('partners')->restrictOnDelete();
            $table->string('document_number', 60)->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'state']);
            $table->index(['partner_id']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('purchase_orders'));

        DB::statement(
            'CREATE UNIQUE INDEX purchase_orders_document_number_unique ON purchase_orders (document_number) '.
            'WHERE document_number IS NOT NULL'
        );

        DB::statement(
            'ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_total_amount_non_negative_check '.
            'CHECK (total_amount >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
