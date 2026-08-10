<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori produk — T2.3. CLAUDE.md §7 — `product_categories` adalah
     * tabel REPLICATED: HQ satu-satunya penulis, node cabang membaca
     * replika read-only (`ProductCategoryPolicy` menegakkan ini lewat
     * `GuardsMasterDataWrites`, sama seperti `partners`).
     *
     * Tanpa `branch_id` — REPLICATED global, bukan branch-scoped.
     * Tanpa `created_by`/`updated_by` — mengikuti preseden `branches`/
     * `partners`: diaudit lewat `audit_logs` (trait `Auditable`), bukan
     * stempel per baris.
     *
     * `parent_id` self-referencing — mendukung subkategori (mis. Komponen
     * > Motherboard). Nullable: kategori level teratas tidak punya induk.
     * `restrictOnDelete` — kategori yang masih punya anak tidak boleh
     * dihapus fisik (selaras R5, jalur hapus hanya lewat soft delete).
     *
     * Constraint FK ditambahkan lewat `Schema::table()` terpisah, BUKAN
     * inline `constrained()` di dalam `Schema::create()` — untuk foreign
     * key self-referencing, Laravel/Postgres mengeksekusi `ALTER TABLE
     * ADD CONSTRAINT ... FOREIGN KEY` sebelum `ALTER TABLE ADD PRIMARY
     * KEY` yang berasal dari `uuidPrimaryKey()` (`->primary()` diproses
     * sebagai command terakhir dalam blueprint yang sama), menghasilkan
     * SQLSTATE 42830 "no unique constraint matching given keys" karena PK
     * belum ada saat FK dibuat. Memisah ke `Schema::table()` menjamin
     * PRIMARY KEY tabel ini sudah commit lebih dulu.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('parent_id');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('product_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
