<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Produk/barang dagangan (T2.5). Tidak punya kolom harga beli — lihat
 * catatan di migration (§16 peringatan #5). Harga perolehan per batch ada
 * di `StockBatch` (T3.1), FIFO (R2).
 */
class Product extends Model
{
    use Auditable;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;
    use HasUuidV7;
    use SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'product_category_id',
        'base_unit_id',
        'selling_price',
        'is_active',
        'is_serialized',
        'reorder_level',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_serialized' => 'boolean',
            'reorder_level' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * @return BelongsTo<Unit>
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }
}
