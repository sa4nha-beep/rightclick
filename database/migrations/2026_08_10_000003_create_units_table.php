<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satuan pengukuran — T2.4. CLAUDE.md §7 — `units` adalah tabel
     * REPLICATED: HQ satu-satunya penulis, node cabang membaca replika
     * read-only (`UnitPolicy` menegakkan ini lewat `GuardsMasterDataWrites`,
     * sama seperti `partners`/`product_categories`).
     *
     * Tanpa `branch_id` — REPLICATED global. Tanpa `created_by`/
     * `updated_by` — diaudit lewat `audit_logs` (trait `Auditable`).
     *
     * Sengaja tanpa kolom konversi (mis. `conversion_factor`) — setiap
     * produk merujuk tepat satu `base_unit_id` (T2.5), tidak ada fitur
     * jual-lintas-satuan di MVP. Menambahkannya sekarang tanpa kebutuhan
     * nyata akan jadi struktur yang tidak pernah terpakai.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
