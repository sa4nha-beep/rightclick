<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\StockTransferLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris dokumen kirim transfer (T3.6). Tanpa API tulis independen
 * (`StockTransferLinePolicy`).
 *
 * @property string $stock_transfer_id
 * @property string $product_id
 * @property string $quantity
 * @property array<int, string>|null $serial_numbers
 */
class StockTransferLine extends Model
{
    /** @use HasFactory<StockTransferLineFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'quantity',
        'serial_numbers',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'serial_numbers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<StockTransfer, $this>
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<StockTransferLineBatch, $this>
     */
    public function lineBatches(): HasMany
    {
        return $this->hasMany(StockTransferLineBatch::class);
    }
}
