<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jasa — T2.7. CLAUDE.md §7 — `services` adalah tabel REPLICATED: HQ
     * satu-satunya penulis, node cabang membaca replika read-only
     * (`ServicePolicy` menegakkan ini lewat `GuardsMasterDataWrites`,
     * sama seperti `products`).
     *
     * PENTING — pembeda dengan "Servis" (CLAUDE.md §3, di luar MVP):
     * "Servis" merujuk MODUL alur kerja servis (tiket, penjadwalan
     * teknisi, tracking penyelesaian) — itu tetap berjalan manual di
     * toko selama MVP, TIDAK dibangun di sini. Tabel `services` di sini
     * hanyalah katalog harga jasa yang bisa dijual sebagai baris
     * transaksi di POS (T4) — setara "daftar harga", bukan sistem
     * booking. Konsumsi part untuk servis manual dicatat lewat stock
     * adjustment berkategori `service_consumption_manual` (§3), bukan
     * lewat relasi apa pun ke tabel ini.
     *
     * Tanpa `branch_id` — REPLICATED global. Tanpa `created_by`/
     * `updated_by` — diaudit lewat `audit_logs` (trait `Auditable`).
     * `price` NOT NULL + CHECK > 0, mengikuti pola `products.selling_price`.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->decimal('price', 18, 2);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('is_active');
        });

        DB::statement(
            'ALTER TABLE services ADD CONSTRAINT services_price_positive_check '.
            'CHECK (price > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
