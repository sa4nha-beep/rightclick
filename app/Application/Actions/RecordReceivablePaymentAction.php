<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\CashLedgerService;
use App\Application\Services\OutboxService;
use App\Domain\Finance\Enums\CashEntryType;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Infrastructure\Persistence\Models\Receivable;
use App\Infrastructure\Persistence\Models\ReceivablePayment;
use Illuminate\Support\Facades\DB;

/**
 * Catat satu peristiwa pelunasan piutang (T5.5, direstrukturisasi menutup
 * gap FR-M11a-05 — `HS-DB-RIGHTCLICK-v1.0` §4.6: "satu setoran terhadap
 * beberapa tagihan"). SATU pemanggilan bisa mengalokasikan SATU pembayaran
 * ke BANYAK `Receivable` sekaligus — beda mendasar dari desain lama
 * (`receivable_payments.sale_id`, satu baris = satu Sale).
 *
 * TIDAK menyentuh `DocumentStateService`/`Sale` sama sekali — action ini
 * murni bekerja atas baris `Receivable` (cache turunan, lihat docblock
 * modelnya) yang HANYA ada selama `Sale` induknya masih final dengan sisa
 * piutang (`VoidSaleAction` soft-delete baris ini saat Sale dibatalkan).
 * Karena itu tidak perlu memuat/mengunci `Sale` sama sekali — keberadaan
 * `Receivable` yang belum soft-delete SUDAH membuktikan Sale masih final.
 *
 * Validasi partner: seluruh alokasi dalam SATU pemanggilan WAJIB berasal
 * dari partner yang sama — satu peristiwa pembayaran (satu setoran/nota
 * transfer) secara bisnis selalu berasal dari satu pihak.
 *
 * `PaymentMethod` dipakai ulang langsung dari `App\Domain\Sales\Enums` —
 * di sini justru "kandang aslinya" (Sales).
 *
 * `CashLedgerService`: `method='cash'` menerbitkan `CashEntry` kas MASUK
 * (`CashEntryType::ReceivableCollection`) yang merujuk `ReceivablePayment`
 * (header) — BUKAN `Sale` seperti desain lama, karena satu pembayaran kini
 * bisa mencakup banyak Sale sekaligus; header adalah satu-satunya dokumen
 * tunggal yang benar-benar mewakili peristiwa kas ini.
 *
 * `OutboxService`: aggregate `receivable_payment.recorded` tetap
 * `ReceivablePayment` (header) — `$relations=['allocations']` melampirkan
 * seluruh baris alokasi. `CashEntry` (bila tunai) sudah otomatis
 * ditemukan `OutboxService` lewat `reference_type`/`reference_id` karena
 * SEKARANG menunjuk header itu sendiri — TIDAK PERLU lagi dilampirkan
 * manual lewat `$extra` seperti desain lama (yang harus menambal karena
 * `CashEntry` menunjuk `Sale`, bukan aggregate event).
 */
final class RecordReceivablePaymentAction
{
    public function __construct(
        private readonly CashLedgerService $cashLedger,
        private readonly OutboxService $outbox,
    ) {}

    /**
     * @param  array<int, array{receivable_id: string, amount: string}>  $allocations
     */
    public function execute(array $allocations, string $method, string $totalAmount, ?string $referenceNo = null): ReceivablePayment
    {
        return DB::transaction(function () use ($allocations, $method, $totalAmount, $referenceNo) {
            if ($allocations === []) {
                throw new SaleValidationException('Alokasi pelunasan wajib diisi minimal satu baris.');
            }

            if (bccomp($totalAmount, '0', 2) <= 0) {
                throw new SaleValidationException('Jumlah pelunasan harus lebih besar dari nol.');
            }

            $sumAllocations = '0';
            foreach ($allocations as $allocation) {
                $sumAllocations = bcadd($sumAllocations, (string) $allocation['amount'], 2);
            }

            if (bccomp($sumAllocations, $totalAmount, 2) !== 0) {
                throw new SaleValidationException(sprintf(
                    'Total alokasi Rp%s tidak sama dengan jumlah pelunasan Rp%s.',
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
                    throw new SaleValidationException('Jumlah alokasi per tagihan harus lebih besar dari nol.');
                }

                $receivable = Receivable::query()
                    ->whereKey((string) $allocation['receivable_id'])
                    ->lockForUpdate()
                    ->first();

                if ($receivable === null) {
                    throw new SaleValidationException('Tagihan piutang tidak ditemukan atau sudah tidak berlaku.');
                }

                if ($partnerId === null) {
                    $partnerId = $receivable->partner_id;
                    $branch = $receivable->loadMissing('branch')->branch;
                } elseif ($receivable->partner_id !== $partnerId) {
                    throw new SaleValidationException('Seluruh alokasi dalam satu pembayaran wajib berasal dari partner yang sama.');
                }

                if (bccomp($amount, (string) $receivable->outstanding_amount, 2) > 0) {
                    throw new SaleValidationException(sprintf(
                        'Alokasi Rp%s melebihi sisa piutang Rp%s pada tagihan ini.',
                        $amount,
                        $receivable->outstanding_amount,
                    ));
                }

                $lockedAllocations[] = ['receivable' => $receivable, 'amount' => $amount];
            }

            $receivablePayment = ReceivablePayment::create([
                'method' => $paymentMethod->value,
                'amount' => $totalAmount,
                'reference_no' => $referenceNo,
            ]);

            foreach ($lockedAllocations as $entry) {
                /** @var Receivable $receivable */
                $receivable = $entry['receivable'];
                $amount = $entry['amount'];

                $receivable->paid_amount = bcadd((string) $receivable->paid_amount, $amount, 2);
                $receivable->outstanding_amount = bcsub((string) $receivable->original_amount, (string) $receivable->paid_amount, 2);
                $receivable->payment_status = $this->resolveStatus($receivable);
                $receivable->save();

                $receivablePayment->allocations()->create([
                    'receivable_id' => $receivable->id,
                    'amount' => $amount,
                ]);
            }

            if ($paymentMethod === PaymentMethod::Cash) {
                $this->cashLedger->record(
                    $branch,
                    $totalAmount,
                    CashEntryType::ReceivableCollection,
                    now(),
                    $receivablePayment,
                );
            }

            $this->outbox->record($branch, $receivablePayment, 'receivable_payment.recorded', ['allocations']);

            return $receivablePayment->fresh(['allocations']);
        });
    }

    private function resolveStatus(Receivable $receivable): PaymentStatus
    {
        if (bccomp((string) $receivable->paid_amount, '0', 2) <= 0) {
            return PaymentStatus::Unpaid;
        }

        if (bccomp((string) $receivable->paid_amount, (string) $receivable->original_amount, 2) >= 0) {
            return PaymentStatus::Paid;
        }

        return PaymentStatus::Partial;
    }
}
