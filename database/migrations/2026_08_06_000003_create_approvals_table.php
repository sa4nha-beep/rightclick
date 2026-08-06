<?php

use App\Infrastructure\Persistence\Support\MigrationMacros;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alur approval generik (T1.9). DB Design §4.1 — `approvals`, kelas
     * SYNCED: dokumen apa pun (adjustment, PO, perubahan harga, diskon di
     * atas ambang) mengajukan permintaan lewat relasi polimorfik
     * `approvable_type`/`approvable_id`, ditangani `ApprovalService`.
     *
     * `uuidMorphs('approvable')` — kolom relasi polimorfik + indeks
     * (`approvable_type`, `approvable_id`) bawaan Laravel, cocok karena
     * seluruh primary key RIGHTCLICK adalah UUID v7 (R#7 lapisan DB Design §1).
     *
     * AP-04 (PRD §12.2) — "Penolakan wajib disertai alasan yang terlihat
     * oleh pemohon": ditegakkan constraint C7-serupa lewat
     * `MigrationMacros::approvalRejectionReasonCheckSql()`, bukan hanya di
     * `ApprovalService`.
     */
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->uuidMorphs('approvable');
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            // Dasbor penyetuju (AP-03): daftar permintaan tertunda per cabang.
            $table->index(['branch_id', 'status']);
        });

        DB::statement(MigrationMacros::approvalRejectionReasonCheckSql('approvals'));
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
