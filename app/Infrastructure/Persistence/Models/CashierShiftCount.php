<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\CashierShiftCountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris hitung kas per pecahan saat tutup shift (bagian AC-16 asli). Tanpa
 * soft delete/`Auditable` sendiri — dibuat SEKALI di dalam transaksi
 * `CloseCashierShiftAction::execute()`, sebelum shift ditransisikan ke
 * `final` (lihat catatan migration). Tidak pernah dimanipulasi lewat API
 * publik di luar action itu (`CashierShiftCountPolicy`: create/update/delete
 * selalu `false`, pola sama `StockOpnameLinePolicy`).
 *
 * @property string $cashier_shift_id
 * @property string $denomination
 * @property int $quantity
 * @property string $subtotal
 */
class CashierShiftCount extends Model
{
    /** @use HasFactory<CashierShiftCountFactory> */
    use HasFactory;

    use HasUuidV7;

    protected $fillable = [
        'cashier_shift_id',
        'denomination',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'denomination' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<CashierShift, $this>
     */
    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }
}
