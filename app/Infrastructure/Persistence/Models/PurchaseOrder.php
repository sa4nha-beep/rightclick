<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Purchase order (T5.1) — draft → final → void (R4). TH4 (CLAUDE.md §10,
 * `po.max_admin`) ditegakkan `FinalizePurchaseOrderAction`: total di atas
 * ambang membuat dokumen tetap draft menunggu approval
 * (`approve_purchase_order`) alih-alih langsung difinalisasi.
 *
 * `total_amount` TIDAK memasuki `stock_batches` sama sekali — PO adalah
 * rencana pembelian, bukan penerimaan barang. Nilai batch sebenarnya
 * (termasuk PPN, R2) baru ditentukan saat faktur pembelian masuk (T5.2).
 *
 * @property string $branch_id
 * @property string $partner_id
 * @property string|null $document_number
 * @property string $total_amount
 * @property DocumentState $state
 */
class PurchaseOrder extends Model
{
    use Auditable;
    use BelongsToBranch;
    use HasDocumentState;

    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
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
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }
}
