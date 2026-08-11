<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentStatus;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\PayableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Sisi AP dari `Receivable` — treatment simetris penuh (lihat docblocknya
 * untuk alasan desain lengkap). Dibuat SEKALI oleh
 * `FinalizePurchaseInvoiceAction` untuk SETIAP faktur yang difinalisasi
 * (bukan hanya yang bersaldo > 0 — lihat catatan migration).
 *
 * @property string $purchase_invoice_id
 * @property string $partner_id
 * @property string $original_amount
 * @property string $paid_amount
 * @property string $outstanding_amount
 * @property PaymentStatus $payment_status
 * @property Carbon|null $due_date
 */
class Payable extends Model
{
    use Auditable;
    use BelongsToBranch;

    /** @use HasFactory<PayableFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'purchase_invoice_id',
        'partner_id',
        'original_amount',
        'paid_amount',
        'outstanding_amount',
        'payment_status',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'due_date' => 'date',
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
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * @return HasMany<PurchasePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PurchasePaymentAllocation::class);
    }
}
