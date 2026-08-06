<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\ApprovalStatus;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Permintaan approval generik (T1.9). Dokumen apa pun yang melampaui ambang
 * (TH1–TH5c) mengajukan lewat relasi polimorfik `approvable()` — transaksi
 * itu sendiri TIDAK ditolak (AP-01), hanya menunggu keputusan di sini.
 *
 * Tidak memakai `TracksUserActions`/`userStamps()` — tabel ini punya kolom
 * aktor sendiri yang lebih spesifik (`requested_by`, `approver_id`), bukan
 * `created_by`/`updated_by` generik. Tidak memakai soft delete: DB Design
 * §4.1 tidak mencantumkan `deleted_at` untuk tabel ini — keputusan approval
 * adalah jejak permanen, sama rasionalnya dengan `audit_logs`.
 *
 * `Auditable` (T1.6): permintaan, persetujuan, dan penolakan approval adalah
 * "aksi sensitif" per R11 — dicatat otomatis, bukan lewat panggilan manual
 * tersebar di `ApprovalService`.
 */
class Approval extends Model
{
    use Auditable;
    use BelongsToBranch;
    use HasUuidV7;

    protected $fillable = [
        'branch_id',
        'approvable_type',
        'approvable_id',
        'requested_by',
        'approver_id',
        'status',
        'reason',
        'requested_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User>
     */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
