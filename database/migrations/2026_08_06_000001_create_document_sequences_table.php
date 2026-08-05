<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel penomoran dokumen terpusat (T1.7). DB Design §4.1 — `document_sequences`,
     * kelas LOCAL: satu penghitung per (cabang, jenis dokumen, periode).
     *
     * Nomor diambil oleh App\Application\Services\DocumentNumberService dengan
     * `SELECT ... FOR UPDATE` pada baris sequence, di dalam transaksi yang sama
     * dengan penyimpanan dokumen (R6, DB Design §8.1). Prefix cabang pada nomor
     * mencegah tabrakan antar node saat konsolidasi di HQ.
     *
     * Sengaja tanpa `created_at`/`updated_at`/`deleted_at`: ini penghitung
     * internal, bukan dokumen transaksi. Spesifikasi eksplisit tabel pada
     * DB Design §4.1 hanya memuat lima kolom di bawah; tidak ada peran yang
     * meng-CRUD tabel ini sehingga tidak ada Eloquent model maupun Policy —
     * layanan menulisnya langsung melalui query builder.
     */
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->string('document_type', 50);
            $table->string('period', 7); // 'YYYY-MM', mis. '2026-08'
            $table->integer('last_number')->default(0);

            // C12 — satu baris penghitung per cabang, jenis, dan periode.
            $table->unique(['branch_id', 'document_type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
