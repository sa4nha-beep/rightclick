<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan sistem yang dapat diubah tanpa deploy ulang (T1.9). DB
     * Design §4.1 — `settings`, kelas REPLICATED: HQ satu-satunya penulis,
     * node cabang membaca replika read-only (`SettingPolicy` menegakkan ini
     * lewat `GuardsMasterDataWrites`, sama seperti `branches`/`users`).
     *
     * Global per kunci — TIDAK branch-scoped (tidak ada kolom `branch_id`,
     * unique murni pada `key`). Nilai ambang TH1–TH5c (HS-PRD-RIGHTCLICK-v1.0
     * §5.1) disimpan di sini lewat `SettingSeeder`.
     *
     * Sengaja tanpa `created_by`/`updated_by`/soft delete: DB Design §4.1
     * hanya mencantumkan lima kolom di bawah untuk tabel ini — perubahan
     * nilai ambang sudah tercatat lewat `AuditLogService` (observer T1.6),
     * bukan lewat stempel per baris.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->string('key', 100)->unique();
            $table->jsonb('value');
            $table->text('description')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
