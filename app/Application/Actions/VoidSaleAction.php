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
 * Ditolak bila `Receivable` terkait sudah punya `paid_amount > 0` — pola
 * sama `VoidPurchaseInvoiceAction` (T5.3): pelunasan piutang bersifat
 * immutable tanpa mekanisme koreksi individual, jadi membatalkan
 * penjualan yang sudah menerima cicilan pelunasan akan meninggalkan
 * pembayaran yang tidak jelas dasarnya. Setelah guard lolos (berarti
 * `paid_amount` masih nol), baris `Receivable` ikut di-soft-delete — tidak
 * ada lagi yang perlu ditagih atas Sale yang dibatalkan (penutup gap
 * FR-M11a-05, lihat docblock `Receivable`).
 *
 * `Receivable` yang baru di-soft-delete DILAMPIRKAN LEWAT `$extra`, BUKAN
 * `$relations` — bug nyata ditemukan saat menulis ini: `OutboxService::record()`
 * memanggil `$document->refresh()` yang me-refresh ULANG setiap relasi yang
 * SUDAH dimuat (`Model::refresh()` bawaan Laravel), dan query default
 * relasi `SoftDeletes` MENGECUALIKAN baris yang baru saja di-soft-delete —
 * hasilnya payload akan berisi `null`, bukan snapshot `deleted_at` yang
 * benar, dan HQ TIDAK PERNAH tahu piutang ini sudah dibatalkan. Snapshot
 * diambil manual dari objek `$receivable` yang SAMA yang baru dipanggil
 * `->delete()` (sudah punya `deleted_at` di memori sebelum query apa pun
 * berikutnya menimpanya).
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
            $receivable = $sale->receivable;

            if ($receivable !== null && bccomp((string) $receivable->paid_amount, '0', 2) > 0) {
                throw new SaleValidationException(
                    'Penjualan ini sudah menerima pelunasan piutang — tidak dapat dibatalkan selama ada cicilan tercatat.',
                );
            }

            $receivable?->delete();

            $this->stockLedger->reverseForReference($sale, $sale);
            $this->cashLedger->reverseForReference($sale, $sale);
            $this->documentStates->void($sale, $reason);

            $extra = $receivable !== null ? ['receivable' => $receivable->attributesToArray()] : [];
            $this->outbox->record($sale->branch, $sale, 'sale.voided', extra: $extra);

            return $sale->fresh();
        });
    }
}
