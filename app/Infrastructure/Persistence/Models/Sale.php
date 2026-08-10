<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penjualan retail (T4.1) — draft → final → void (R4). Tidak ada kolom PPN
 * di mana pun (R13). `subtotal`/`discount_amount`/`total_amount` dikunci
 * saat finalisasi (`FinalizeSaleAction`) — lihat catatan migration.
 *
 * `amount_paid`/`balance_due`/`payment_status` (T4.2, DP) juga dikunci saat
 * finalisasi — lihat catatan migration `add_payment_tracking_to_sales_table`
 * untuk alasan tidak ada tabel `receivables` terpisah di sini (Fase 5).
 *
 * @property string $branch_id
 * @property string $cashier_shift_id
 * @property string|null $partner_id
 * @property string|null $document_number
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $total_amount
 * @property string $amount_paid
 * @property string $balance_due
 * @property PaymentStatus $payment_status
 * @property DocumentState $state
 */
class Sale extends Model
{
    use Auditable;
    use BelongsToBranch;
    use HasDocumentState;

    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
        'cashier_shift_id',
        'partner_id',
        'document_number',
        'subtotal',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'balance_due',
        'payment_status',
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
            'finalized_at' => 'datetime',
            'voided_at' => 'datetime',
            'payment_status' => PaymentStatus::class,
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
     * @return BelongsTo<CashierShift, $this>
     */
    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }
}
