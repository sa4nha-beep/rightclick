<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\CashierShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shift kasir (T4.1) — draft (terbuka) → final (tertutup) → void (R4).
 * `CloseCashierShiftAction` mengisi `closing_cash_counted`/
 * `closing_cash_expected`/`variance` dan memfinalisasi sekaligus — begitu
 * `final`, `HasDocumentState` menolak edit langsung field manapun selain
 * lewat void (AC-16: "selisih tidak dapat disesuaikan").
 *
 * @property string $branch_id
 * @property string $cashier_id
 * @property string|null $document_number
 * @property string $opening_cash
 * @property string|null $closing_cash_counted
 * @property string|null $closing_cash_expected
 * @property string|null $variance
 * @property DocumentState $state
 */
class CashierShift extends Model
{
    use Auditable;
    use BelongsToBranch;
    use HasDocumentState;

    /** @use HasFactory<CashierShiftFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
        'cashier_id',
        'document_number',
        'opening_cash',
        'opened_at',
        'closing_cash_counted',
        'closing_cash_expected',
        'variance',
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
            'opened_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<CashierShiftCount, $this>
     */
    public function counts(): HasMany
    {
        return $this->hasMany(CashierShiftCount::class);
    }
}
