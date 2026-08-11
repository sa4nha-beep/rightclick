<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Penutup gap T2.9/UT13/NFR-04 terhadap `HS-TASKS-RIGHTCLICK-v1.1`
     * ("pencarian < 1 detik pada 20.000 SKU") dan `HS-DB-RIGHTCLICK-v1.0`
     * §7 ("indeks GIN trigram pada name; B-tree pada sku"). `sku` sudah
     * punya indeks unik implisit (T2.5) — hanya `name` yang butuh indeks
     * baru untuk mendukung `ilike('%term%')` yang sudah dipakai
     * `PosTerminal::products()` (T4.4) sejak awal, tanpa perubahan query.
     *
     * `PosTerminal::products()` juga mencari lewat `sku` dengan pola
     * `%term%` yang sama (bukan hanya prefix) — B-tree unik pada `sku`
     * TIDAK bisa mempercepat substring match di tengah/akhir string, jadi
     * `sku` turut diberi indeks trigram di sini, sedikit menyimpang dari
     * teks literal `HS-DB-RIGHTCLICK-v1.0` demi konsisten dengan query
     * yang SUNGGUHAN dipakai — bukan spekulasi kebutuhan baru.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX products_name_trgm_idx ON products USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX products_sku_trgm_idx ON products USING gin (sku gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_sku_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS products_name_trgm_idx');
    }
};
