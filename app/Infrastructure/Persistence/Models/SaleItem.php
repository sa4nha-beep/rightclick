<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\SaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris penjualan (T4.1). Tepat satu dari `product`/`service` terisi
 * (CHECK constraint di migration — lihat catatan di sana untuk alasan tidak
 * memakai morph polymorphic). Tanpa soft delete/`Auditable` sendiri — sama
 * alasan dengan `StockAdjustmentLine`: tidak ada API tulis independen
 * (`SaleItemPolicy`: create/update/delete selalu `false`).
 *
 * `line_total` diisi otomatis (`quantity * unit_price`) lewat model event
 * `saving` — form tidak perlu menghitungnya manual.
 *
 * @property string $sale_id
 * @property string|null $product_id
 * @property string|null $service_id
 * @property string $quantity
 * @property string $unit_price
 * @property string|null $unit_cost_snapshot
 * @property string $line_total
 * @property array<int, string>|null $serial_numbers
 */
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'sale_id',
        'product_id',
        'service_id',
        'quantity',
        'unit_price',
        'unit_cost_snapshot',
        'line_total',
        'serial_numbers',
    ];

    protected function casts(): array
    {
        return [
            'serial_numbers' => 'array',
        ];
    }

    public static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->line_total = bcmul((string) $item->quantity, (string) $item->unit_price, 2);
        });
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<SaleReturnLine, $this>
     */
    public function returnLines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class);
    }
}
