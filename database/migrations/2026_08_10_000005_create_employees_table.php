<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Karyawan — T2.6. CLAUDE.md §7 — `employees` adalah tabel REPLICATED:
     * HQ satu-satunya penulis, node cabang membaca replika read-only
     * (`EmployeePolicy` menegakkan ini lewat `GuardsMasterDataWrites`,
     * sama seperti `partners`/`products`).
     *
     * Tanpa `branch_id` — HRIS & Payroll di luar MVP (CLAUDE.md §3), jadi
     * tidak ada kebutuhan nyata untuk penugasan cabang karyawan yang
     * ditegakkan sistem saat ini. Menambahkannya sekarang tanpa fitur
     * yang memakainya akan jadi kolom yang tidak pernah terpakai.
     *
     * `user_id` nullable — karyawan tidak wajib punya akun login (mis.
     * staf non-sistem); yang punya akun terhubung lewat kolom ini.
     *
     * `id_number` (KTP/SIM) nullable + unique — memungkinkan input data
     * karyawan sebelum dokumen identitas lengkap tersedia saat onboarding,
     * tetap mencegah duplikat begitu terisi (NULL tidak melanggar unique
     * constraint di Postgres).
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('id_number', 30)->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('position', 100);
            $table->string('department', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->date('hired_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
