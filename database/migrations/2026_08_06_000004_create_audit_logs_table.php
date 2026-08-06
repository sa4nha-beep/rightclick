<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            // Append-only table: no soft delete, no updated_at (R5, R11)
            $table->uuidPrimaryKey();

            // Siapa yang melakukan aksi
            $table->uuid('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();

            // Jenis aksi dan objek yang dipengaruhi
            $table->string('action'); // Casted to AuditAction enum: 'created', 'updated', 'deleted', 'voided', 'approved', 'access_denied', dst.
            $table->string('model_type'); // Fully qualified class name, mis. App\Infrastructure\Persistence\Models\User
            $table->uuid('model_id'); // ID dari record yang dipengaruhi

            // Nilai lama dan baru (R11)
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            // Cabang — untuk scoping audit log per cabang (BranchScope)
            $table->uuid('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();

            // Metadata tambahan: alasan void, IP address, user agent, dst.
            $table->jsonb('metadata')->nullable();

            // Hanya created_at, tidak ada updated_at — append-only, no mutations
            $table->timestamp('created_at');

            // Index untuk query: branch + action, actor, model
            $table->index(['branch_id', 'action']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
