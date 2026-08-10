<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partner (pemasok dan pelanggan) — T2.2. CLAUDE.md §7 — `partners`
     * adalah tabel REPLICATED: HQ satu-satunya penulis, node cabang membaca
     * replika read-only (`PartnerPolicy` menegakkan ini lewat
     * `GuardsMasterDataWrites`, sama seperti `branches`/`settings`).
     *
     * Tanpa `branch_id` — REPLICATED global, bukan branch-scoped
     * (`MigrationMacros::branchId()` sengaja dilewati, lihat komentar
     * macro tersebut yang menyebut `partners` eksplisit).
     *
     * Tanpa `created_by`/`updated_by` — mengikuti preseden `branches`:
     * REPLICATED master data yang diaudit lewat `audit_logs` (trait
     * `Auditable`), bukan lewat stempel per baris.
     *
     * Pengecualian offline (API Spec §8, `POST /api/v1/sync/partner-upsert`):
     * node cabang boleh membuat Partner lokal saat HQ tak terjangkau. Itu
     * jalur khusus lewat service sinkronisasi (T5.x), bukan lewat
     * `PartnerPolicy::create()` biasa — tidak dibangun di sini.
     */
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('partner_type', 20);
            $table->string('tax_id', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->decimal('credit_limit', 18, 2)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('partner_type');
        });

        // DB Design §7 — status/tipe berbentuk varchar + CHECK constraint,
        // dipetakan ke PHP Enum (App\Domain\Shared\Enums\PartnerType).
        DB::statement(
            'ALTER TABLE partners ADD CONSTRAINT partners_partner_type_check '.
            "CHECK (partner_type IN ('supplier', 'customer', 'both'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
