<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Inventory\Enums\StockAdjustmentDirection;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\StockAdjustmentLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris penyesuaian stok (T3.5). Tanpa soft delete/`Auditable` sendiri —
 * sama alasan dengan `StockOpnameLine`. Tidak ada API tulis independen
 * (`StockAdjustmentLinePolicy`: create/update/delete selalu `false`).
 *
 * @property string $stock_adjustment_id
 * @property string $product_id
 * @property StockAdjustmentDirection $direction
 * @property string $quantity
 * @property string|null $unit_cost
 * @property string $reason
 * @property array<int, string>|null $serial_numbers
 */
class StockAdjustmentLine extends Model
{
    /** @use HasFactory<StockAdjustmentLineFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'direction',
        'quantity',
        'unit_cost',
        'reason',
        'serial_numbers',
    ];

    protected function casts(): array
    {
        return [
            'direction' => StockAdjustmentDirection::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'serial_numbers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<StockAdjustment, $this>
     */
    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
