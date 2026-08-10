<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\GoodsReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penerimaan barang (T5.2, simpul kritis) — draft → final → void (R4).
 * `FinalizeGoodsReceiptAction` adalah dokumen yang MEMANGGIL
 * `StockLedgerService::receive()` — `goods_receipt_lines.unit_cost`
 * (TERMASUK PPN, R2/AC-09) mengalir langsung ke `stock_batches`.
 * `purchaseInvoice()` (T5.2) adalah catatan hutang/AP formal yang menaut
 * balik ke sini SETELAH stok bergerak, bukan pemicu ledger kedua.
 *
 * TIDAK ADA alur approval/ambang — CLAUDE.md §10 tidak menetapkan TH untuk
 * goods receipt.
 *
 * @property string $branch_id
 * @property string|null $purchase_order_id
 * @property string $partner_id
 * @property string|null $document_number
 * @property string $total_amount
 * @property DocumentState $state
 */
class GoodsReceipt extends Model
{
    use Auditable;
    use BelongsToBranch;
    use HasDocumentState;

    /** @use HasFactory<GoodsReceiptFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
        'purchase_order_id',
        'partner_id',
        'document_number',
        'total_amount',
        'state',
        'finalized_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'finalized_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    /**
     * @return HasOne<PurchaseInvoice, $this>
     */
    public function purchaseInvoice(): HasOne
    {
        return $this->hasOne(PurchaseInvoice::class);
    }
}
