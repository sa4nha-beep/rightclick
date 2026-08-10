<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\ReceivablePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris pelunasan piutang (T5.5). Pola PERSIS `PurchasePayment` (T5.3),
 * sisi kebalikannya — ditulis langsung lewat `RecordReceivablePaymentAction`
 * setelah `Sale` induknya final, bukan byproduct finalisasi. Karena itu
 * `ReceivablePaymentPolicy::create()` TIDAK selalu `false` seperti
 * `SalePaymentPolicy` — digerbang `record_cash_entry` sungguhan.
 *
 * Tanpa soft delete/`userStamps()` — immutable, tanpa mekanisme koreksi
 * individual di T5.5.
 *
 * @property string $sale_id
 * @property PaymentMethod $method
 * @property string $amount
 * @property string|null $reference_no
 */
class ReceivablePayment extends Model
{
    /** @use HasFactory<ReceivablePaymentFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'reference_no',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
