<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\BelongsToBranch;
use App\Infrastructure\Persistence\Concerns\HasDocumentState;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use App\Infrastructure\Persistence\Concerns\TracksUserActions;
use Database\Factories\Infrastructure\Persistence\Models\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penjualan retail (T4.1) — draft → final → void (R4). Tidak ada kolom PPN
 * di mana pun (R13). `subtotal`/`discount_amount`/`total_amount` dikunci
 * saat finalisasi (`FinalizeSaleAction`) — lihat catatan migration.
 *
 * `amount_paid`/`balance_due`/`payment_status` (T4.2, DP) juga dikunci saat
 * finalisasi.
 *
 * `balance_due` (T4.2) adalah piutang AWAL saat sale ini difinalisasi —
 * TIDAK PERNAH diperbarui lagi (R4). Bila `> 0`, `FinalizeSaleAction`
 * membuat satu baris `Receivable` (penutup gap `HS-DB-RIGHTCLICK-v1.0`
 * §4.6/FR-M11a-05) yang melacak sisa piutang KINI — diperbarui
 * `RecordReceivablePaymentAction` setiap alokasi pembayaran baru. Satu
 * peristiwa pembayaran (`ReceivablePayment`) bisa dialokasikan ke BANYAK
 * Sale sekaligus, bukan lagi terikat satu-ke-satu (T5.5 lama). Lihat
 * `amountCollected()`/`remainingReceivable()`/`receivableStatus()` yang
 * mendelegasikan ke `receivable()`.
 *
 * @property string $branch_id
 * @property string $cashier_shift_id
 * @property string|null $partner_id
 * @property string|null $document_number
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $total_amount
 * @property string $amount_paid
 * @property string $balance_due
 * @property PaymentStatus $payment_status
 * @property DocumentState $state
 * @property-read Receivable|null $receivable
 */
class Sale extends Model
{
    use Auditable;
    use BelongsToBranch;
    use HasDocumentState;

    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;
    use TracksUserActions;

    protected $fillable = [
        'branch_id',
        'cashier_shift_id',
        'partner_id',
        'document_number',
        'subtotal',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'balance_due',
        'payment_status',
        'state',
        'finalized_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
            'voided_at' => 'datetime',
            'payment_status' => PaymentStatus::class,
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
     * @return BelongsTo<CashierShift, $this>
     */
    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * @return HasOne<Receivable, $this>
     */
    public function receivable(): HasOne
    {
        return $this->hasOne(Receivable::class);
    }

    /**
     * Total piutang yang sudah dikumpulkan SETELAH finalisasi — dibaca dari
     * cache `Receivable::$paid_amount` (rebuild piutang, lihat docblock
     * `Receivable`), bukan lagi `SUM(receivable_payments.amount)` langsung
     * (kolom `receivable_payments.sale_id` sudah dihapus — satu peristiwa
     * pembayaran kini bisa mencakup banyak Sale sekaligus, FR-M11a-05).
     * Tanpa baris `Receivable` (balance_due lunas penuh saat finalisasi) —
     * tidak ada yang perlu dikumpulkan lagi.
     */
    public function amountCollected(): string
    {
        $receivable = $this->receivable;

        return $receivable === null ? '0.00' : (string) $receivable->paid_amount;
    }

    /**
     * Sisa piutang KINI — dibaca langsung dari cache `Receivable::$outstanding_amount`.
     */
    public function remainingReceivable(): string
    {
        $receivable = $this->receivable;

        return $receivable === null ? '0.00' : (string) $receivable->outstanding_amount;
    }

    /**
     * Status piutang KINI — BEDA dari `payment_status` tersimpan (T4.2,
     * status DP pada saat finalisasi, historis dan tidak pernah berubah).
     * Dibaca dari cache `Receivable::$payment_status`; tanpa baris
     * `Receivable` berarti lunas penuh saat finalisasi.
     */
    public function receivableStatus(): PaymentStatus
    {
        $receivable = $this->receivable;

        return $receivable === null ? PaymentStatus::Paid : $receivable->payment_status;
    }

    /**
     * Saldo piutang total dari satu pelanggan di satu cabang — SUM
     * `receivables.outstanding_amount` langsung (SATU query, menggantikan
     * loop N+1 per Sale pada implementasi lama). Dasar "saldo piutang per
     * partner" (T5.5), pola persis `PurchaseInvoice::outstandingBalanceForPartner()`.
     */
    public static function outstandingReceivableForPartner(string $branchId, string $partnerId): string
    {
        return (string) Receivable::query()
            ->where('branch_id', $branchId)
            ->where('partner_id', $partnerId)
            ->sum('outstanding_amount');
    }
}
