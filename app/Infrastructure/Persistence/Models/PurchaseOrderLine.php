<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\PurchaseOrderLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris purchase order (T5.1). Tanpa soft delete/`Auditable` sendiri —
 * sama alasan dengan `SaleItem`/`StockAdjustmentLine`: tidak ada API tulis
 * independen (`PurchaseOrderLinePolicy`: create/update/delete selalu
 * `false`).
 *
 * `unit_price` adalah harga PESANAN — bukan `unit_cost` batch (R2). Nilai
 * ini tidak pernah mengalir ke `stock_batches`; itu ditentukan sendiri saat
 * faktur pembelian masuk (T5.2).
 *
 * `line_total` diisi otomatis (`quantity * unit_price`) lewat model event
 * `saving`, sama pola dengan `SaleItem::line_total`.
 *
 * @property string $purchase_order_id
 * @property string $product_id
 * @property string $quantity
 * @property string $unit_price
 * @property string $line_total
 */
class PurchaseOrderLine extends Model
{
    /** @use HasFactory<PurchaseOrderLineFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public static function booted(): void
    {
        static::saving(function (self $line): void {
            $line->line_total = bcmul((string) $line->quantity, (string) $line->unit_price, 2);
        });
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
