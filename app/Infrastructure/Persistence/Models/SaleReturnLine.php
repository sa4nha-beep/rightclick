<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\SaleReturnLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris retur penjualan (T4.3, AC-18). Tanpa soft delete/`Auditable`
 * sendiri — sama pola dengan `SaleItem`/`StockAdjustmentLine`, tanpa API
 * tulis independen (`SaleReturnLinePolicy`: create/update/delete selalu
 * `false`).
 *
 * @property string $sale_return_id
 * @property string $sale_item_id
 * @property string $quantity
 * @property string|null $unit_cost
 * @property string|null $unit_price
 * @property string|null $refund_amount
 * @property string $reason
 * @property array<int, string>|null $serial_numbers
 */
class SaleReturnLine extends Model
{
    /** @use HasFactory<SaleReturnLineFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'quantity',
        'unit_cost',
        'unit_price',
        'refund_amount',
        'reason',
        'serial_numbers',
    ];

    protected function casts(): array
    {
        return [
            'serial_numbers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SaleReturn, $this>
     */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /**
     * @return BelongsTo<SaleItem, $this>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
