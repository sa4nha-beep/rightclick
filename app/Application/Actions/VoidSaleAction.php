<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\CashLedgerService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Application\Services\StockLedgerService;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Infrastructure\Persistence\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Void penjualan final (T4.1, R4). Sama pola dengan
 * `VoidStockAdjustmentAction` — mengembalikan barang ke stok lewat mutasi
 * berlawanan (bukan menghapus mutasi lama, DB Design §8.3), lalu
 * memfinalisasi status void.
 *
 * `CashLedgerService::reverseForReference()` (T5.4 retrofit) — bila
 * penjualan ini punya `CashEntry` kas masuk (pembayaran tunai), void
 * menerbitkan entri kas KELUAR berlawanan yang merujuk balik ke `$sale`
 * ini — tanpa ini, kas yang sudah dibalikkan lewat void tetap tercatat
 * sebagai masuk selamanya di ledger kas.
 *
 * Ditolak bila sudah ada `receivable_payments` tercatat (T5.5) — pola
 * sama `VoidPurchaseInvoiceAction` (T5.3): pelunasan piutang bersifat
 * immutable tanpa mekanisme koreksi individual, jadi membatalkan
 * penjualan yang sudah menerima cicilan pelunasan akan meninggalkan
 * pembayaran yang tidak jelas dasarnya.
 *
 * `OutboxService` (T5.7 retrofit, simpul kritis): `sale.voided`.
 *
 * TIDAK menyesuaikan ulang `cashier_shifts.closing_cash_expected`/
 * `variance` bila shift terkait sudah ditutup sebelum void ini terjadi —
 * keterbatasan yang didokumentasikan, bukan kelalaian: rekonsiliasi shift
 * pasca-tutup adalah kasus tepi yang ditunda (lih. `FinalizeSaleAction`
 * untuk gap T4.1 lain yang serupa gaya pendokumentasiannya).
 */
final class VoidSaleAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly CashLedgerService $cashLedger,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(Sale $sale, string $reason): Sale
    {
        return DB::transaction(function () use ($sale, $reason) {
            if ($sale->receivablePayments()->exists()) {
                throw new SaleValidationException(
                    'Penjualan ini sudah menerima pelunasan piutang — tidak dapat dibatalkan selama ada cicilan tercatat.',
                );
            }

            $this->stockLedger->reverseForReference($sale, $sale);
            $this->cashLedger->reverseForReference($sale, $sale);
            $this->documentStates->void($sale, $reason);
            $this->outbox->record($sale->branch, $sale, 'sale.voided');

            return $sale->fresh();
        });
    }
}
