<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\StockBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Batch stok (T3.1). Satu-satunya penulis adalah `StockLedgerService`
 * (T3.2, R1) — model ini tidak boleh dibuat/diubah langsung dari kode lain
 * (lihat `StockBatchPolicy`: create/update selalu ditolak).
 *
 * `unit_cost` TERMASUK PPN (R2). FIFO membaca `qty_remaining > 0 ORDER BY
 * received_at ASC` — lihat index `stock_batches_fifo_index` pada migration.
 *
 * @property string $branch_id
 * @property string $product_id
 * @property string $qty_received
 * @property string $qty_remaining
 * @property string $unit_cost
 * @property string $reference_type
 * @property string $reference_id
 */
class StockBatch extends Model
{
    use Auditable;
    use BelongsToBranch;

    /** @use HasFactory<StockBatchFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
        'product_id',
        'received_at',
        'unit_cost',
        'qty_received',
        'qty_remaining',
        'reference_type',
        'reference_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'unit_cost' => 'decimal:2',
            'qty_received' => 'decimal:4',
            'qty_remaining' => 'decimal:4',
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Dokumen yang menerbitkan batch ini (StockOpname, StockAdjustment,
     * StockTransferReceipt, kelak GoodsReceipt).
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
