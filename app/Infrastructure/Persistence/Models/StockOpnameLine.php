<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\StockOpnameLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris stock opname (T3.4). Tanpa soft delete/`Auditable` sendiri — hidup
 * di dalam siklus draft/final header `StockOpname` (lihat catatan migration).
 * Tidak pernah dimanipulasi lewat API publik di luar form dokumen induk
 * (`StockOpnameLinePolicy`: create/update/delete selalu `false`).
 *
 * @property string $stock_opname_id
 * @property string $product_id
 * @property string $system_qty
 * @property string $counted_qty
 * @property string|null $unit_cost
 * @property string|null $reason
 * @property array<int, string>|null $serial_numbers
 */
class StockOpnameLine extends Model
{
    /** @use HasFactory<StockOpnameLineFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'system_qty',
        'counted_qty',
        'unit_cost',
        'reason',
        'serial_numbers',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:4',
            'counted_qty' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'serial_numbers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<StockOpname, $this>
     */
    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
