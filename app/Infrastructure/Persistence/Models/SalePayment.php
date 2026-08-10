<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\SalePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris pembayaran penjualan (multi-payment, T4.1). Tanpa soft delete/
 * `Auditable` sendiri — sama pola dengan `SaleItem`, tanpa API tulis
 * independen (`SalePaymentPolicy`: create/update/delete selalu `false`).
 *
 * @property string $sale_id
 * @property PaymentMethod $method
 * @property string $amount
 * @property string|null $reference_no
 */
class SalePayment extends Model
{
    /** @use HasFactory<SalePaymentFactory> */
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
