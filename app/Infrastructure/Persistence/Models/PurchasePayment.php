<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\PurchasePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header peristiwa pembayaran hutang (T5.3, direstrukturisasi menutup gap
 * FR-M11a-05 — treatment simetris penuh `ReceivablePayment`, lihat
 * docblocknya untuk alasan desain lengkap). Kolom `purchase_invoice_id`
 * dihapus — baris ini murni header, alokasi ke `Payable` lewat
 * `allocations()`.
 *
 * Tanpa soft delete/`userStamps()` — immutable, tanpa mekanisme koreksi
 * individual di T5.3 (lihat catatan migration).
 *
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
     * @return HasMany<PurchasePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PurchasePaymentAllocation::class);
    }
}
