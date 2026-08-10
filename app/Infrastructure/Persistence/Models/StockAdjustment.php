<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\StockAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penyesuaian stok (T3.5) — draft → final → void (R4). TH3/TH3b
 * (CLAUDE.md §10) ditegakkan `FinalizeStockAdjustmentAction`: nilai per
 * dokumen dan kumulatif per Admin per bulan di atas ambang membuat dokumen
 * tetap draft menunggu approval (`approve_stock_adjustment`, PT15) alih-alih
 * langsung diterapkan.
 *
 * @property string $branch_id
 * @property string|null $document_number
 * @property DocumentState $state
 */
class StockAdjustment extends Model
{
    use Auditable;
    use BelongsToBranch;
    use HasDocumentState;

    /** @use HasFactory<StockAdjustmentFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
        'document_number',
        'state',
        'finalized_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return HasMany<StockAdjustmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class);
    }
}
