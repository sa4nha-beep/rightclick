<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Shared\Enums\AuditAction;
use App\Infrastructure\Persistence\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * API untuk logging aksi sensitif (R11, T1.11).
 *
 * Setiap aksi yang mengubah state sistem (create, update, delete, soft delete,
 * restore, finalize, void, approve, reject) dicatat di sini dengan:
 * - Siapa (actor_id)
 * - Apa (action enum)
 * - Pada apa (model_type, model_id)
 * - Nilai lama dan baru (old_values, new_values)
 * - Konteks tambahan (metadata: alasan, IP, user agent, dst.)
 *
 * Append-only: tidak ada update atau delete setelah tercatat. Koreksi aksi
 * tercatat sebagai aksi baru (void, delete, dst.), bukan penghapusan.
 *
 * Audit log disimpan di tabel SYNCED dan direplikasi ke HQ via outbox,
 * sehingga riwayat lengkap tersedia di pusat.
 */
final class AuditService
{
    /**
     * Catat aksi terhadap sebuah model.
     *
     * @param Model $model Model yang dipengaruhi
     * @param AuditAction $action Jenis aksi
     * @param array<string, mixed>|null $oldValues Nilai sebelum (optional)
     * @param array<string, mixed>|null $newValues Nilai sesudah (optional)
     * @param array<string, mixed>|null $metadata Konteks tambahan: alasan, tujuan, dst.
     * @param string|null $actorId ID pengguna yang bertindak (default: Auth::id())
     * @param string|null $branchId ID cabang (default: dari model atau auth user)
     */
    public function log(
        Model $model,
        AuditAction $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?string $actorId = null,
        ?string $branchId = null,
    ): AuditLog {
        // Tentukan branch_id: parameter eksplisit > model->branch_id > model->default_branch_id > auth user's branch
        $resolvedBranchId = $branchId ?? $model->branch_id ?? $model->default_branch_id ?? Auth::user()?->default_branch_id;

        return AuditLog::create([
            'actor_id' => $actorId ?? Auth::id(),
            'action' => $action,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'branch_id' => $resolvedBranchId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Catat aksi tanpa model spesifik (mis. access denied saat gate gagal).
     *
     * Dipakai oleh middleware atau gate authorization yang mendeteksi
     * akses tanpa permission, bukan dari model observer.
     *
     * @param string $modelType Fully qualified class name dari model yang diakses
     * @param string $modelId ID record yang tidak dapat diakses
     * @param AuditAction $action Aksi yang dicoba (biasanya AccessDenied)
     * @param array<string, mixed>|null $metadata Konteks: permission diminta, alasan, dst.
     * @param string|null $branchId ID cabang (default: Auth::user()->branch_id jika ada)
     * @param string|null $actorId ID pengguna (default: Auth::id())
     */
    public function logAccessDenied(
        string $modelType,
        string $modelId,
        ?array $metadata = null,
        ?string $branchId = null,
        ?string $actorId = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actorId ?? Auth::id(),
            'action' => AuditAction::AccessDenied,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => null,
            'new_values' => null,
            'branch_id' => $branchId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
