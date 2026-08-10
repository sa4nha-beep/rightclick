<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\StockTransferReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dokumen TERIMA transfer stok (T3.6, R12). `branch_id` = cabang tujuan
 * (BEDA dari `stockTransfer->branch_id` yang merupakan cabang asal).
 * MVP: satu transfer hanya bisa punya satu receipt (`unique` di migration)
 * — tidak ada penerimaan sebagian.
 *
 * @property string $branch_id
 * @property string $stock_transfer_id
 * @property string|null $document_number
 * @property DocumentState $state
 */
class StockTransferReceipt extends Model
{
    use Auditable;
    use BelongsToBranch;

    use HasDocumentState;

    /** @use HasFactory<StockTransferReceiptFactory> */
    use HasFactory;
    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
        'stock_transfer_id',
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
     * @return BelongsTo<StockTransfer, $this>
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }
}
