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
     * Penjualan retail — T4.1. Draft → final → void (R4). `document_number`
     * (prefix `SAL`, sudah ada di `DocumentType` sejak awal) hanya terisi
     * saat difinalisasi (`FinalizeSaleAction`).
     *
     * `cashier_shift_id` WAJIB — R8/§2 mensyaratkan setiap penjualan
     * bertanggung jawab pada satu shift kasir; `FinalizeSaleAction` menolak
     * finalisasi bila shift terkait sudah tidak `draft` (ditutup).
     *
     * `partner_id` nullable — retail walk-in TANPA data pelanggan adalah
     * kasus utama (§2 "Kasir: transaksi cepat"), bukan pengecualian.
     *
     * `subtotal`/`discount_amount`/`total_amount` TIDAK dihitung ulang
     * otomatis dari baris saat draft diedit — nilainya nol sampai
     * `FinalizeSaleAction` menjumlahkan `sale_items.line_total` dan
     * mengunci totalnya, sama pola dengan `document_number`. `discount_amount`
     * BOLEH diisi saat draft (kasir/admin menentukan diskon sebelum
     * finalisasi) — CLAUDE.md §10 "ambang diskon berlaku pada TOTAL
     * transaksi, bukan per baris", ditegakkan `FinalizeSaleAction` (T4.2),
     * BUKAN di sini.
     *
     * TIDAK ADA kolom PPN di mana pun (R13) — dilarang mutlak, bukan celah
     * yang belum diisi.
     *
     * Multi-payment (`sale_payments`) dan DP/piutang parsial: T4.1 hanya
     * mendukung pembayaran LUNAS (jumlah `sale_payments` harus persis sama
     * dengan `total_amount` saat finalisasi) — DP/piutang sengaja ditunda ke
     * T4.2, didokumentasikan eksplisit di `FinalizeSaleAction`.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('cashier_shift_id')->constrained('cashier_shifts')->restrictOnDelete();
            $table->foreignUuid('partner_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->string('document_number', 60)->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->documentStateColumns();
            $table->userStamps();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'state']);
            $table->index(['cashier_shift_id']);
        });

        DB::statement(MigrationMacros::documentStateVoidCheckSql('sales'));

        DB::statement(
            'CREATE UNIQUE INDEX sales_document_number_unique ON sales (document_number) '.
            'WHERE document_number IS NOT NULL'
        );

        DB::statement(
            'ALTER TABLE sales ADD CONSTRAINT sales_amounts_non_negative_check '.
            'CHECK (subtotal >= 0 AND discount_amount >= 0 AND total_amount >= 0)'
        );

        // TIDAK ADA CHECK `discount_amount <= subtotal` di sini (sengaja) —
        // `subtotal` bernilai 0 sepanjang draft (baru dihitung
        // `FinalizeSaleAction` saat finalisasi, lihat docblock di atas).
        // Kasir/Admin boleh mengisi `discount_amount` SEBELUM subtotal ada;
        // memaksa constraint ini di database akan menolak draft yang sedang
        // dibangun secara normal. `FinalizeSaleAction` sendiri menolak
        // `total_amount` negatif (diskon melebihi subtotal) sebelum
        // mengunci dokumen.
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
