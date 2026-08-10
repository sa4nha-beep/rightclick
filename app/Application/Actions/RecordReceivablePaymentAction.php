<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\CashLedgerService;
use App\Application\Services\OutboxService;
use App\Domain\Finance\Enums\CashEntryType;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\ReceivablePayment;
use App\Infrastructure\Persistence\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Catat satu pelunasan piutang (T5.5) atas `Sale` yang SUDAH final dan
 * punya sisa piutang (`balance_due` > 0 dari DP, T4.2). Pola PERSIS
 * `RecordPurchasePaymentAction` (T5.3), sisi kebalikannya — tidak
 * menyentuh `DocumentStateService` sama sekali, karena `Sale` induk sudah
 * final dan TETAP final (R4); aksi ini hanya menambah baris anak baru.
 *
 * `PaymentMethod` dipakai ulang langsung dari `App\Domain\Sales\Enums` —
 * di sini justru "kandang aslinya" (Sales), beda dari `RecordPurchasePaymentAction`
 * yang meminjam lintas domain — tidak ada catatan promosi `Domain\Shared`
 * yang relevan untuk aksi ini secara spesifik.
 *
 * `CashLedgerService`: `method='cash'` menerbitkan `CashEntry` kas MASUK
 * (`CashEntryType::ReceivableCollection`, BUKAN `SalePayment` — dua
 * peristiwa kas berbeda meski sama-sama dari penjualan, lihat docblock
 * enum) yang merujuk `Sale` ini. Pembayaran non-tunai TIDAK menyentuh kas
 * fisik.
 *
 * `OutboxService` (T5.7 retrofit, simpul kritis): `receivable_payment.recorded`
 * — aggregate-nya baris `ReceivablePayment` yang BARU dibuat, BUKAN `Sale`
 * induk (sudah punya event sendiri, `sale.finalized`, di transaksi
 * TERPISAH saat sale itu sendiri difinalisasi) — sama alasan
 * `RecordPurchasePaymentAction`.
 *
 * Payload (T5.8): `CashEntry` yang tercipta (bila tunai) merujuk `Sale`,
 * BUKAN `ReceivablePayment` — dilampirkan manual lewat `$extra`, sama
 * alasan `RecordPurchasePaymentAction`.
 */
final class RecordReceivablePaymentAction
{
    public function __construct(
        private readonly CashLedgerService $cashLedger,
        private readonly OutboxService $outbox,
    ) {}

    /**
     * @param  array{method: string, amount: string, reference_no?: string|null}  $payment
     */
    public function execute(Sale $sale, array $payment): ReceivablePayment
    {
        return DB::transaction(function () use ($sale, $payment) {
            $locked = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('branch');

            if ($locked->state !== DocumentState::Final) {
                throw new SaleValidationException('Hanya penjualan berstatus final yang dapat menerima pelunasan piutang.');
            }

            $amount = (string) $payment['amount'];

            if (bccomp($amount, '0', 2) <= 0) {
                throw new SaleValidationException('Jumlah pelunasan harus lebih besar dari nol.');
            }

            $remaining = $locked->remainingReceivable();

            if (bccomp($amount, $remaining, 2) > 0) {
                throw new SaleValidationException(sprintf(
                    'Pelunasan Rp%s melebihi sisa piutang Rp%s pada penjualan ini.',
                    $amount,
                    $remaining,
                ));
            }

            $method = PaymentMethod::from($payment['method']);

            $receivablePayment = $locked->receivablePayments()->create([
                'method' => $method->value,
                'amount' => $amount,
                'reference_no' => $payment['reference_no'] ?? null,
            ]);

            $extra = [];

            if ($method === PaymentMethod::Cash) {
                $cashEntry = $this->cashLedger->record(
                    $locked->branch,
                    $amount,
                    CashEntryType::ReceivableCollection,
                    now(),
                    $locked,
                );

                $extra['cash_entries'] = [$cashEntry->attributesToArray()];
            }

            $this->outbox->record($locked->branch, $receivablePayment, 'receivable_payment.recorded', extra: $extra);

            return $receivablePayment;
        });
    }
}
