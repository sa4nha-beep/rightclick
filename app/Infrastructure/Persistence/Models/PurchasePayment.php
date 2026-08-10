<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\PurchasePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris pembayaran hutang (T5.3). BEDA dari `SalePayment` — dokumen ini
 * BOLEH ditulis berkali-kali dari waktu ke waktu SETELAH `PurchaseInvoice`
 * induknya final (cicilan), lewat `RecordPurchasePaymentAction` secara
 * langsung, bukan sebagai byproduct finalisasi dokumen induk. Karena itu
 * `PurchasePaymentPolicy::create()` TIDAK selalu `false` seperti
 * `SalePaymentPolicy` — digerbang `record_cash_entry` sungguhan.
 *
 * Tanpa soft delete/`userStamps()` — immutable, tanpa mekanisme koreksi
 * individual di T5.3 (lihat catatan migration).
 *
 * @property string $purchase_invoice_id
 * @property PaymentMethod $method
 * @property string $amount
 * @property string|null $reference_no
 */
class PurchasePayment extends Model
{
    /** @use HasFactory<PurchasePaymentFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'purchase_invoice_id',
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
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }
}
