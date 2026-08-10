<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Inventory\Enums\StockMutationType;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\StockMutationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ledger mutasi stok — satu-satunya sumber kebenaran stok (R1). Append-only,
 * sama bentuknya dengan `AuditLog` (T1.11 note pada model itu): tidak ada
 * soft delete, tidak ada `updated_at`.
 *
 * SATU-SATUNYA jalur tulis yang sah adalah `App\Application\Services\StockLedgerService`
 * (T3.2) — ditegakkan `tests/Arch/StockMutationSingleWriterTest.php`. Model
 * ini TIDAK memakai `Auditable`/`TracksUserActions` — baris ledger sudah
 * merupakan jejak permanennya sendiri (sama seperti `AuditLog` tidak
 * mengaudit dirinya sendiri).
 *
 * Void TIDAK menghapus/mengubah baris lama — `StockLedgerService::reverseForReference()`
 * menerbitkan mutasi baru berarah berlawanan yang merujuk dokumen void
 * (§16 peringatan #9).
 *
 * @property string $branch_id
 * @property string $product_id
 * @property string $stock_batch_id
 * @property string $quantity
 * @property string $unit_cost
 * @property string $reference_type
 * @property string $reference_id
 */
class StockMutation extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<StockMutationFactory> */
    use HasFactory;

    use HasUuidV7;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'product_id',
        'stock_batch_id',
        'mutation_type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'occurred_at',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'mutation_type' => StockMutationType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<StockBatch, $this>
     */
    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
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
     * Dokumen yang menerbitkan mutasi ini.
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
