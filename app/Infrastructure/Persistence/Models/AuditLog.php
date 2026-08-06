<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\AuditAction;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak permanen aksi sensitif (T1.11, R11).
 *
 * Append-only table — tidak ada soft delete, tidak ada updated_at.
 * Mirip dengan stock_mutations: historis yang tidak dapat diubah.
 *
 * SYNCED (CLAUDE.md §7) — ditulis di cabang, disinkronkan ke HQ lewat outbox.
 * Diakses di banyak tempat: policy authorization (access_denied), business
 * logic (perubahan dokumen state), operational monitoring.
 *
 * Tidak memakai TracksUserActions — kolom aktor di sini lebih spesifik
 * (`actor_id` saja, bukan created_by/updated_by).
 */
class AuditLog extends Model
{
    use BelongsToBranch;
    use HasUuidV7;

    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'branch_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
