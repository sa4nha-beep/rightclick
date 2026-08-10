<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Database\Factories\Infrastructure\Persistence\Models\ProcessedEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger idempotensi sisi HQ (T5.8, CLAUDE.md §8). `id` BUKAN dibangkitkan
 * di sini — nilainya `outbox_events.id` cabang pengirim (lihat docblock
 * migration). Append-only, sama pola `StockMutation`/`AuditLog`/`CashEntry`
 * — tidak ada soft delete, tidak ada `updated_at`.
 *
 * SATU-SATUNYA jalur tulis yang sah adalah `SyncEventProcessor` —
 * ditegakkan `tests/Arch/ProcessedEventSingleWriterTest.php`.
 *
 * @property string $id
 * @property string $branch_id
 * @property string $event_type
 * @property string $aggregate_type
 * @property string $aggregate_id
 */
class ProcessedEvent extends Model
{
    /** @use HasFactory<ProcessedEventFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'branch_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'processed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
