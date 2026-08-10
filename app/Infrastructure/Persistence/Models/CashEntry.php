<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Finance\Enums\CashEntryType;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\CashEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ledger kas — T5.4, AC-21. Append-only, sama bentuknya dengan
 * `StockMutation`/`AuditLog`: tidak ada soft delete, tidak ada `updated_at`.
 *
 * SATU-SATUNYA jalur tulis yang sah adalah
 * `App\Application\Services\CashLedgerService` — ditegakkan
 * `tests/Arch/CashEntrySingleWriterTest.php`. Model ini TIDAK memakai
 * `Auditable`/`TracksUserActions` — baris ledger sudah merupakan jejak
 * permanennya sendiri (sama seperti `StockMutation`).
 *
 * `amount` BERTANDA (positif=masuk, negatif=keluar) — lihat docblock
 * migration.
 *
 * @property string $branch_id
 * @property string $amount
 * @property string $reference_type
 * @property string $reference_id
 */
class CashEntry extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<CashEntryFactory> */
    use HasFactory;

    use HasUuidV7;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'entry_type',
        'amount',
        'reference_type',
        'reference_id',
        'occurred_at',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => CashEntryType::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Dokumen induk yang menerbitkan entri ini (`Sale`/`PurchaseInvoice`).
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
