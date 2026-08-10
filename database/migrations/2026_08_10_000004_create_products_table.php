<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produk/barang dagangan — T2.5. CLAUDE.md §7 — `products` adalah
     * tabel REPLICATED: HQ satu-satunya penulis, node cabang membaca
     * replika read-only (`ProductPolicy` menegakkan ini lewat
     * `GuardsMasterDataWrites`, sama seperti `partners`).
     *
     * Tanpa `branch_id` — REPLICATED global. Tanpa `created_by`/
     * `updated_by` — diaudit lewat `audit_logs` (trait `Auditable`).
     *
     * §16 peringatan #5 — WAJIB DIPATUHI: "Produk tidak punya kolom harga
     * beli. Harga perolehan ada di batch." Tabel ini SENGAJA tidak punya
     * kolom cost/purchase_price/hpp apa pun — nilai perolehan hidup di
     * `stock_batches.unit_cost` (T3.1), FIFO per batch (R2). Menambah
     * kolom biaya di sini akan membuat dua sumber kebenaran biaya yang
     * bisa berbeda nilai.
     *
     * `selling_price` NOT NULL + CHECK > 0 — harga jual wajib ada dan
     * positif. Perubahan harga di atas ambang TH5a (naik >10%) / TH5b
     * (turun >5%) / TH5c (di bawah HPP batch tertua) memerlukan approval
     * Owner (CLAUDE.md §10) — belum ditegakkan di migration/model ini,
     * lihat catatan di `ProductPolicy`.
     *
     * `is_serialized` — flag agar baris transaksi (T4 sales, T3 stock)
     * tahu produk ini wajib diisi serial number (R3: "Serial dicatat
     * sebagai field pada baris transaksi sejak MVP").
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->string('sku', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignUuid('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->foreignUuid('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('selling_price', 18, 2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_serialized')->default(false);
            $table->decimal('reorder_level', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('product_category_id');
            $table->index('is_active');
        });

        DB::statement(
            'ALTER TABLE products ADD CONSTRAINT products_selling_price_positive_check '.
            'CHECK (selling_price > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
