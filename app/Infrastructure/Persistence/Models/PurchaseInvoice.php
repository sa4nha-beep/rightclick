<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\PurchaseInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Faktur pembelian (T5.2) — draft → final → void (R4). Catatan hutang/AP
 * FORMAL, BUKAN pemicu ledger kedua — stok sudah bergerak lewat
 * `goods_receipt()`. `total_amount` DIKUNCI dari `goods_receipts.total_amount`
 * saat `FinalizePurchaseInvoiceAction`, bukan dihitung dari baris sendiri —
 * dokumen ini SENGAJA tanpa tabel `*_lines` sendiri (beda dari
 * `PurchaseOrder`/`GoodsReceipt`): rincian produk sudah tercatat lengkap di
 * `goods_receipt_lines`, mendata ulang di sini hanya akan mengulang sumber
 * kebenaran yang sama tanpa nilai tambah untuk kebutuhan AP (T5.3) yang
 * hanya memerlukan total per faktur.
 *
 * `invoice_number`/`invoice_date` adalah identitas PADA FAKTUR FISIK
 * PEMASOK — beda dari `document_number` (nomor internal RIGHTCLICK).
 *
 * @property string $branch_id
 * @property string $goods_receipt_id
 * @property string $partner_id
 * @property string $invoice_number
 * @property Carbon $invoice_date
 * @property string|null $document_number
 * @property string $total_amount
 * @property DocumentState $state
 */
class PurchaseInvoice extends Model
{
    use Auditable;
    use BelongsToBranch;
    use HasDocumentState;

    /** @use HasFactory<PurchaseInvoiceFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
        'goods_receipt_id',
        'partner_id',
        'invoice_number',
        'invoice_date',
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
            'invoice_date' => 'date',
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
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
