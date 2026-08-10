<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\GoodsReceiptLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris penerimaan barang (T5.2, simpul kritis). Tanpa soft delete/
 * `Auditable` sendiri — tidak ada API tulis independen
 * (`GoodsReceiptLinePolicy`: create/update/delete selalu `false`).
 *
 * `unit_cost` di sini adalah nilai yang SEBENARNYA mengalir ke
 * `stock_batches` (R2/AC-09, TERMASUK PPN) — BEDA dari
 * `PurchaseOrderLine::$unit_price` yang murni rencana.
 *
 * `serial_numbers` (R3/T3.7) — sisi "naik", wajib untuk produk serial.
 *
 * `line_total` diisi otomatis (`quantity * unit_cost`) lewat model event
 * `saving`, sama pola `SaleItem`/`PurchaseOrderLine`.
 *
 * @property string $goods_receipt_id
 * @property string $product_id
 * @property string $quantity
 * @property string $unit_cost
 * @property string $line_total
 * @property array<int, string>|null $serial_numbers
 */
class GoodsReceiptLine extends Model
{
    /** @use HasFactory<GoodsReceiptLineFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'goods_receipt_id',
        'product_id',
        'quantity',
        'unit_cost',
        'line_total',
        'serial_numbers',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'serial_numbers' => 'array',
        ];
    }

    public static function booted(): void
    {
        static::saving(function (self $line): void {
            $line->line_total = bcmul((string) $line->quantity, (string) $line->unit_cost, 2);
        });
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
