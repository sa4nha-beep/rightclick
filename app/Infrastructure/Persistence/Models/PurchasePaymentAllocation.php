<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\PurchasePaymentAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sisi AP dari `ReceivablePaymentAllocation` — treatment simetris penuh
 * (lihat docblocknya untuk alasan desain lengkap).
 *
 * @property string $purchase_payment_id
 * @property string $payable_id
 * @property string $amount
 */
class PurchasePaymentAllocation extends Model
{
    /** @use HasFactory<PurchasePaymentAllocationFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'purchase_payment_id',
        'payable_id',
        'amount',
    ];

    /**
     * @return BelongsTo<PurchasePayment, $this>
     */
    public function purchasePayment(): BelongsTo
    {
        return $this->belongsTo(PurchasePayment::class);
    }

    /**
     * @return BelongsTo<Payable, $this>
     */
    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }
}
