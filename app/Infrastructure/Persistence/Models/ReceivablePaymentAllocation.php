<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\ReceivablePaymentAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris alokasi pembayaran piutang (penutup gap FR-M11a-05) — menghubungkan
 * satu header `ReceivablePayment` ke satu `Receivable`. Satu
 * `ReceivablePayment` bisa punya BANYAK baris alokasi (ke banyak
 * `Receivable` sekaligus). Tanpa API tulis independen — hanya dibuat lewat
 * `RecordReceivablePaymentAction` (`ReceivablePaymentAllocationPolicy`:
 * create/update/delete selalu `false`, pola sama `StockOpnameLinePolicy`).
 *
 * @property string $receivable_payment_id
 * @property string $receivable_id
 * @property string $amount
 */
class ReceivablePaymentAllocation extends Model
{
    /** @use HasFactory<ReceivablePaymentAllocationFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'receivable_payment_id',
        'receivable_id',
        'amount',
    ];

    /**
     * @return BelongsTo<ReceivablePayment, $this>
     */
    public function receivablePayment(): BelongsTo
    {
        return $this->belongsTo(ReceivablePayment::class);
    }

    /**
     * @return BelongsTo<Receivable, $this>
     */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }
}
