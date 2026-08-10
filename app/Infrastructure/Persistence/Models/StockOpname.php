<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Inventory\Enums\StockOpnameType;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\StockOpnameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock opname (T3.4) — draft → final → void (R4). `type=opening_balance`
 * (R9) mensyaratkan permission `adjust_opening_balance`
 * (`StockOpnamePolicy::finalize()`), berbeda dari opname berkala biasa.
 *
 * Finalisasi lewat `FinalizeStockOpnameAction` (bukan langsung
 * `DocumentStateService::finalize()`) — baris berselisih perlu diproses
 * lewat `StockLedgerService` lebih dulu (AC-12: selisih tanpa alasan
 * ditolak).
 *
 * @property string $branch_id
 * @property StockOpnameType $type
 * @property string|null $document_number
 * @property DocumentState $state
 */
class StockOpname extends Model
{
    use Auditable;
    use BelongsToBranch;
    use HasDocumentState;

    /** @use HasFactory<StockOpnameFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
        'type',
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
            'type' => StockOpnameType::class,
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
     * @return HasMany<StockOpnameLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockOpnameLine::class);
    }
}
