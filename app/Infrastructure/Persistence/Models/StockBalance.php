<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\StockBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cache turunan kuantitas stok (LOCAL, CLAUDE.md §7, T3.2). Hanya ditulis
 * `StockLedgerService` atau `php artisan stock:rebuild-balances` — tidak
 * ada halaman create/edit di Filament (`StockBalancePolicy`, T3.3).
 *
 * Tidak memakai `Auditable` — ini cache turunan, bukan dokumen bisnis;
 * jejak kebenarannya ada di `stock_mutations` (append-only, sudah teraudit
 * secara struktural lewat keberadaannya sendiri).
 */
class StockBalance extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<StockBalanceFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'branch_id',
        'product_id',
        'qty_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
