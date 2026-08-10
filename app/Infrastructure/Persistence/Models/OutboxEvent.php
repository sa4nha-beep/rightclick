<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sync\Enums\OutboxEventStatus;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\OutboxEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Outbox transaksi (T5.7, simpul kritis). SATU-SATUNYA jalur tulis yang
 * sah adalah `App\Application\Services\OutboxService` — ditegakkan
 * `tests/Arch/OutboxEventSingleWriterTest.php` (pola sama R1/T3.2).
 *
 * `id` (UUID v7) adalah idempotency key — akan dikirim sebagai `event_id`
 * pada payload `POST /api/v1/sync/events` (T5.8).
 *
 * BUKAN append-only murni seperti `StockMutation`/`CashEntry` — `status`
 * berubah dari waktu ke waktu (worker sinkronisasi T5.8 menandai
 * `sent`/`failed`), jadi tetap memakai `updated_at` (beda dari
 * `stock_mutations`/`cash_entries` yang `$timestamps = false`).
 *
 * @property string $branch_id
 * @property string $event_type
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property array<string, mixed> $payload
 * @property OutboxEventStatus $status
 */
class OutboxEvent extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<OutboxEventFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'branch_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => OutboxEventStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Dokumen induk yang menerbitkan event ini.
     *
     * @return MorphTo<Model, $this>
     */
    public function aggregate(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'aggregate_type', 'aggregate_id');
    }
}
