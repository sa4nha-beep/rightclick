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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penjualan retail (T4.1) — draft → final → void (R4). Tidak ada kolom PPN
 * di mana pun (R13). `subtotal`/`discount_amount`/`total_amount` dikunci
 * saat finalisasi (`FinalizeSaleAction`) — lihat catatan migration.
 *
 * `amount_paid`/`balance_due`/`payment_status` (T4.2, DP) juga dikunci saat
 * finalisasi — lihat catatan migration `add_payment_tracking_to_sales_table`
 * untuk alasan tidak ada tabel `receivables` terpisah di sini (Fase 5).
 *
 * `balance_due` (T4.2) adalah piutang AWAL saat sale ini difinalisasi —
 * TIDAK PERNAH diperbarui lagi (R4). Pelunasan BELAKANGAN dicatat di
 * `receivable_payments` (T5.5, pola persis `PurchasePayment`/T5.3); sisa
 * piutang KINI dihitung, bukan disimpan — lihat `amountCollected()`/
 * `remainingReceivable()`/`receivableStatus()`.
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
     * @return HasMany<ReceivablePayment, $this>
     */
    public function receivablePayments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    /**
     * Total piutang yang sudah dikumpulkan SETELAH finalisasi — SUM
     * `receivable_payments.amount`, bukan kolom tersimpan (lihat docblock
     * kelas).
     */
    public function amountCollected(): string
    {
        return (string) $this->receivablePayments()->sum('amount');
    }

    /**
     * Sisa piutang KINI — `balance_due` (piutang awal, dikunci saat
     * finalisasi) dikurangi `amountCollected()`.
     */
    public function remainingReceivable(): string
    {
        return bcsub((string) $this->balance_due, $this->amountCollected(), 2);
    }

    /**
     * Status piutang KINI — BEDA dari `payment_status` tersimpan (T4.2,
     * status DP pada saat finalisasi, historis dan tidak pernah berubah).
     * Method ini menghitung ulang dari `remainingReceivable()`.
     */
    public function receivableStatus(): PaymentStatus
    {
        if (bccomp((string) $this->balance_due, '0', 2) <= 0) {
            return PaymentStatus::Paid;
        }

        $collected = $this->amountCollected();

        if (bccomp($collected, '0', 2) <= 0) {
            return PaymentStatus::Unpaid;
        }

        if (bccomp($collected, (string) $this->balance_due, 2) >= 0) {
            return PaymentStatus::Paid;
        }

        return PaymentStatus::Partial;
    }

    /**
     * Saldo piutang total dari satu pelanggan di satu cabang — SUM
     * `remainingReceivable()` atas seluruh penjualan FINAL (non-void) milik
     * pelanggan tersebut. Dasar "saldo piutang per partner" (T5.5), pola
     * persis `PurchaseInvoice::outstandingBalanceForPartner()` (T5.3).
     */
    public static function outstandingReceivableForPartner(string $branchId, string $partnerId): string
    {
        $sales = self::query()
            ->where('branch_id', $branchId)
            ->where('partner_id', $partnerId)
            ->where('state', DocumentState::Final)
            ->get();

        $total = '0';

        foreach ($sales as $sale) {
            $total = bcadd($total, $sale->remainingReceivable(), 2);
        }

        return $total;
    }
}
