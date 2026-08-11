<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentStatus;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\ReceivableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Saldo piutang per `Sale` (penutup gap `HS-DB-RIGHTCLICK-v1.0` §4.6 —
 * lihat docblock migration `create_receivables_table` untuk alasan desain
 * lengkap). Cache tersimpan (`paid_amount`/`outstanding_amount`/
 * `payment_status`), diperbarui `RecordReceivablePaymentAction` setiap
 * alokasi baru — TIDAK ADA API tulis independen di luar itu
 * (`ReceivablePolicy`: create/update/delete selalu `false`).
 *
 * Dibuat SEKALI oleh `FinalizeSaleAction` saat `balance_due > 0`; soft-delete
 * oleh `VoidSaleAction` saat Sale induknya dibatalkan (hanya diizinkan
 * selama `paid_amount = 0`).
 *
 * @property string $sale_id
 * @property string $partner_id
 * @property string $original_amount
 * @property string $paid_amount
 * @property string $outstanding_amount
 * @property PaymentStatus $payment_status
 * @property Carbon|null $due_date
 */
class Receivable extends Model
{
    use Auditable;
    use BelongsToBranch;

    /** @use HasFactory<ReceivableFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'sale_id',
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
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * @return HasMany<ReceivablePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReceivablePaymentAllocation::class);
    }
}
