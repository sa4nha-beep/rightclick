<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\ReceivablePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header peristiwa pelunasan piutang (T5.5, direstrukturisasi menutup gap
 * FR-M11a-05 — lihat docblock migration `receivable_payment_allocations`).
 * SEJAK rebuild ini, baris ini TIDAK LAGI terikat langsung ke satu `Sale`
 * (kolom `sale_id` dihapus) — murni header (method/amount TOTAL/reference_no)
 * yang mencatat SATU peristiwa pembayaran, yang bisa dialokasikan ke BANYAK
 * `Receivable` sekaligus lewat `allocations()`. Ditulis langsung lewat
 * `RecordReceivablePaymentAction`, bukan byproduct finalisasi dokumen induk
 * — karena itu `ReceivablePaymentPolicy::create()` TIDAK selalu `false`
 * seperti `SalePaymentPolicy`, digerbang `record_cash_entry` sungguhan.
 *
 * Tanpa soft delete/`userStamps()` — immutable, tanpa mekanisme koreksi
 * individual di T5.5.
 *
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
     * @return HasMany<ReceivablePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReceivablePaymentAllocation::class);
    }
}
