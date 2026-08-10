<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\StockTransferLineBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian batch sumber terpakai FIFO saat dispatch (T3.6) — lihat catatan
 * migration. Ditulis oleh `DispatchStockTransferAction`, dibaca
 * `ReceiveStockTransferAction`. Tanpa API tulis independen lain.
 *
 * @property string $stock_transfer_line_id
 * @property string $source_stock_batch_id
 * @property string $quantity
 * @property string $unit_cost
 */
class StockTransferLineBatch extends Model
{
    /** @use HasFactory<StockTransferLineBatchFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'stock_transfer_line_id',
        'source_stock_batch_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StockTransferLine, $this>
     */
    public function stockTransferLine(): BelongsTo
    {
        return $this->belongsTo(StockTransferLine::class);
    }

    /**
     * @return BelongsTo<StockBatch, $this>
     */
    public function sourceStockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'source_stock_batch_id');
    }
}
