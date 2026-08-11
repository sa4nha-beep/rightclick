<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\CashLedgerService;
use App\Application\Services\OutboxService;
use App\Domain\Finance\Enums\CashEntryType;
use App\Domain\Procurement\Exceptions\PurchaseInvoiceValidationException;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Infrastructure\Persistence\Models\Payable;
use App\Infrastructure\Persistence\Models\PurchasePayment;
use Illuminate\Support\Facades\DB;

/**
 * Sisi AP dari `RecordReceivablePaymentAction` — treatment simetris penuh
 * (lihat docblocknya untuk alasan desain lengkap). Menutup gap FR-M11a-05:
 * satu peristiwa pembayaran bisa dialokasikan ke BANYAK `Payable` (faktur)
 * sekaligus, bukan lagi satu-ke-satu (`purchase_payments.purchase_invoice_id`
 * lama).
 *
 * `PaymentMethod` dipakai ulang langsung dari `App\Domain\Sales\Enums` —
 * BUKAN diduplikasi ke `Domain\Procurement` (catatan promosi ke
 * `Domain\Shared` tetap berlaku, lihat riwayat T5.3).
 *
 * `CashLedgerService`: `method='cash'` menerbitkan `CashEntry` kas KELUAR
 * (amount negatif) yang merujuk `PurchasePayment` (header) — bukan lagi
 * `PurchaseInvoice`, sama alasan sisi AR.
 */
final class RecordPurchasePaymentAction
{
    public function __construct(
        private readonly CashLedgerService $cashLedger,
        private readonly OutboxService $outbox,
    ) {}

    /**
     * @param  array<int, array{payable_id: string, amount: string}>  $allocations
     */
    public function execute(array $allocations, string $method, string $totalAmount, ?string $referenceNo = null): PurchasePayment
    {
        return DB::transaction(function () use ($allocations, $method, $totalAmount, $referenceNo) {
            if ($allocations === []) {
                throw new PurchaseInvoiceValidationException('Alokasi pembayaran wajib diisi minimal satu baris.');
            }

            if (bccomp($totalAmount, '0', 2) <= 0) {
                throw new PurchaseInvoiceValidationException('Jumlah pembayaran harus lebih besar dari nol.');
            }

            $sumAllocations = '0';
            foreach ($allocations as $allocation) {
                $sumAllocations = bcadd($sumAllocations, (string) $allocation['amount'], 2);
            }

            if (bccomp($sumAllocations, $totalAmount, 2) !== 0) {
                throw new PurchaseInvoiceValidationException(sprintf(
                    'Total alokasi Rp%s tidak sama dengan jumlah pembayaran Rp%s.',
                    $sumAllocations,
                    $totalAmount,
                ));
            }

            $paymentMethod = PaymentMethod::from($method);

            $lockedAllocations = [];
            $partnerId = null;
            $branch = null;

            foreach ($allocations as $allocation) {
                $amount = (string) $allocation['amount'];

                if (bccomp($amount, '0', 2) <= 0) {
                    throw new PurchaseInvoiceValidationException('Jumlah alokasi per faktur harus lebih besar dari nol.');
                }

                $payable = Payable::query()
                    ->whereKey((string) $allocation['payable_id'])
                    ->lockForUpdate()
                    ->first();

                if ($payable === null) {
                    throw new PurchaseInvoiceValidationException('Tagihan hutang tidak ditemukan atau sudah tidak berlaku.');
                }

                if ($partnerId === null) {
                    $partnerId = $payable->partner_id;
                    $branch = $payable->loadMissing('branch')->branch;
                } elseif ($payable->partner_id !== $partnerId) {
                    throw new PurchaseInvoiceValidationException('Seluruh alokasi dalam satu pembayaran wajib berasal dari partner yang sama.');
                }

                if (bccomp($amount, (string) $payable->outstanding_amount, 2) > 0) {
                    throw new PurchaseInvoiceValidationException(sprintf(
                        'Alokasi Rp%s melebihi sisa hutang Rp%s pada faktur ini.',
                        $amount,
                        $payable->outstanding_amount,
                    ));
                }

                $lockedAllocations[] = ['payable' => $payable, 'amount' => $amount];
            }

            $purchasePayment = PurchasePayment::create([
                'method' => $paymentMethod->value,
                'amount' => $totalAmount,
                'reference_no' => $referenceNo,
            ]);

            foreach ($lockedAllocations as $entry) {
                /** @var Payable $payable */
                $payable = $entry['payable'];
                $amount = $entry['amount'];

                $payable->paid_amount = bcadd((string) $payable->paid_amount, $amount, 2);
                $payable->outstanding_amount = bcsub((string) $payable->original_amount, (string) $payable->paid_amount, 2);
                $payable->payment_status = $this->resolveStatus($payable);
                $payable->save();

                $purchasePayment->allocations()->create([
                    'payable_id' => $payable->id,
                    'amount' => $amount,
                ]);
            }

            if ($paymentMethod === PaymentMethod::Cash) {
                $this->cashLedger->record(
                    $branch,
                    bcmul($totalAmount, '-1', 2),
                    CashEntryType::PurchasePayment,
                    now(),
                    $purchasePayment,
                );
            }

            $this->outbox->record($branch, $purchasePayment, 'purchase_payment.recorded', ['allocations']);

            return $purchasePayment->fresh(['allocations']);
        });
    }

    private function resolveStatus(Payable $payable): PaymentStatus
    {
        if (bccomp((string) $payable->paid_amount, '0', 2) <= 0) {
            return PaymentStatus::Unpaid;
        }

        if (bccomp((string) $payable->paid_amount, (string) $payable->original_amount, 2) >= 0) {
            return PaymentStatus::Paid;
        }

        return PaymentStatus::Partial;
    }
}
