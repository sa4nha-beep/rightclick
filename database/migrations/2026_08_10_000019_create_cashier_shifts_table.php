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
     * Shift kasir — T4.1. Draft → final → void (R4), sama pola dengan
     * `stock_adjustments`. `document_number` (prefix `SFT`, self-derived —
     * lihat catatan `DocumentType`) hanya terisi saat shift benar-benar
     * ditutup (`CloseCashierShiftAction`), bukan saat dibuka.
     *
     * `cashier_id` (bukan `created_by`) adalah pemilik bisnis shift — siapa
     * yang bertanggung jawab atas kas laci ini. Dipisah dari `created_by`
     * (siapa yang menekan tombol buka, bisa berbeda mis. Admin membuka
     * shift atas nama Kasir).
     *
     * AC-16 ("selisih shift tercatat sebagai dokumen tersendiri, tidak
     * dapat disesuaikan") ditegakkan STRUKTURAL oleh `HasDocumentState`:
     * begitu `state=final`, `closing_cash_counted`/`closing_cash_expected`/
     * `variance` tidak bisa diedit langsung — koreksi hanya lewat void
     * beralasan (R4), bukan mekanisme khusus shift.
     *
     * Index parsial unik mencegah kasir membuka dua shift sekaligus di
     * cabang yang sama (satu shift `draft` per kasir per cabang).
     */
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('document_number', 60)->nullable();
            $table->decimal('opening_cash', 18, 2);
            $table->timestampTz('opened_at')->useCurrent();
            $table->decimal('closing_cash_counted', 18, 2)->nullable();
            $table->decimal('closing_cash_expected', 18, 2)->nullable();
            $table->decimal('variance', 18, 2)->nullable();
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'state']);
            $table->index(['cashier_id', 'state']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('cashier_shifts'));

        DB::statement(
            'CREATE UNIQUE INDEX cashier_shifts_document_number_unique ON cashier_shifts (document_number) '.
            'WHERE document_number IS NOT NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX cashier_shifts_one_open_per_cashier ON cashier_shifts (branch_id, cashier_id) '.
            "WHERE state = 'draft'"
        );

        DB::statement(
            'ALTER TABLE cashier_shifts ADD CONSTRAINT cashier_shifts_opening_cash_check CHECK (opening_cash >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
    }
};
