<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\SyncStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Status sinkronisasi per cabang, sisi HQ (T5.8). Satu baris per
 * `branch_id` (`unique`), diperbarui di tempat — sumber
 * `GET /api/v1/sync/health`. Lihat docblock migration untuk kenapa
 * `pending_count`/`deferred_count` adalah angka YANG DILAPORKAN cabang,
 * bukan dihitung HQ sendiri.
 *
 * @property string $branch_id
 * @property string|null $last_event_id
 * @property Carbon|null $last_event_at
 * @property Carbon|null $last_seen_at
 * @property int $events_processed_count
 * @property int $pending_count
 * @property int $deferred_count
 */
class SyncState extends Model
{
    /** @use HasFactory<SyncStateFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'branch_id',
        'last_event_id',
        'last_event_at',
        'last_seen_at',
        'events_processed_count',
        'pending_count',
        'deferred_count',
    ];

    protected function casts(): array
    {
        return [
            'last_event_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'events_processed_count' => 'integer',
            'pending_count' => 'integer',
            'deferred_count' => 'integer',
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
