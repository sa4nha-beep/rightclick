<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Application\Services\SerialNumberValidationService;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\SaleReturn;
use App\Infrastructure\Persistence\Models\SaleReturnLine;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;

/**
 * Finalisasi retur penjualan (T4.3, AC-18: "Barang diretur → kembali pada
 * nilai perolehan batch asal, bukan harga jual").
 *
 * `unit_cost` tiap baris DIAMBIL DARI `sale_items.unit_cost_snapshot` milik
 * baris penjualan asal — BUKAN dari harga jual (`sale_items.unit_price`)
 * dan BUKAN dari harga batch TERKINI (yang bisa sudah berubah). Nilai ini
 * yang dikirim ke `StockLedgerService::receive()` — barang masuk kembali ke
 * `stock_batches` pada HPP aslinya, menjaga margin/HPP tetap akurat (R2).
 *
 * `unit_price` tiap baris (untuk `refund_amount`, catatan nilai kembali ke
 * pelanggan) TETAP diambil dari `sale_items.unit_price` (harga jual asli)
 * — nilai yang berbeda, tujuan berbeda, lihat catatan migration.
 *
 * Kuantitas retur divalidasi terhadap SISA yang belum diretur pada baris
 * penjualan itu (kuantitas asli dikurangi retur FINAL sebelumnya) —
 * mencegah retur berulang melebihi yang pernah dibeli.
 */
final class FinalizeSaleReturnAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly SerialNumberValidationService $serialNumbers,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(SaleReturn $saleReturn): SaleReturn
    {
        return DB::transaction(function () use ($saleReturn) {
            $saleReturn->loadMissing(['branch', 'sale', 'lines.saleItem.product']);

            $this->validate($saleReturn);

            $totalRefund = '0';

            foreach ($saleReturn->lines as $line) {
                $saleItem = $line->saleItem;
                $product = $saleItem->product;

                $unitCost = (string) $saleItem->unit_cost_snapshot;
                $unitPrice = (string) $saleItem->unit_price;
                $refundAmount = bcmul((string) $line->quantity, $unitPrice, 2);

                $line->unit_cost = $unitCost;
                $line->unit_price = $unitPrice;
                $line->refund_amount = $refundAmount;
                $line->save();

                $totalRefund = bcadd($totalRefund, $refundAmount, 2);

                $this->stockLedger->receive(
                    $saleReturn->branch,
                    $product,
                    (string) $line->quantity,
                    $unitCost,
                    now(),
                    $saleReturn,
                    StockMutationType::SaleReturn,
                );
            }

            $saleReturn->total_refund = $totalRefund;
            $saleReturn->document_number = $this->documentNumbers->next(DocumentType::SaleReturn, $saleReturn->branch);
            $saleReturn->save();

            $this->documentStates->finalize($saleReturn);

            $this->outbox->record($saleReturn->branch, $saleReturn, 'sale_return.finalized');

            return $saleReturn->fresh(['lines']);
        });
    }

    private function validate(SaleReturn $saleReturn): void
    {
        if ($saleReturn->lines->isEmpty()) {
            throw new SaleValidationException('Retur tanpa baris tidak dapat difinalisasi.');
        }

        if ($saleReturn->sale === null || $saleReturn->sale->state !== DocumentState::Final) {
            throw new SaleValidationException('Penjualan asal harus berstatus final untuk dapat diretur.');
        }

        if ($saleReturn->sale->branch_id !== $saleReturn->branch_id) {
            throw new SaleValidationException('Retur harus berada di cabang yang sama dengan penjualan asal.');
        }

        foreach ($saleReturn->lines as $line) {
            $saleItem = $line->saleItem;

            if ($saleItem === null || $saleItem->sale_id !== $saleReturn->sale_id) {
                throw new SaleValidationException('Baris retur harus merujuk baris penjualan pada dokumen penjualan yang sama.');
            }

            if ($saleItem->product_id === null) {
                throw new SaleValidationException('Baris jasa tidak dapat diretur — hanya produk fisik.');
            }

            if (blank(trim((string) $line->reason))) {
                throw new SaleValidationException(sprintf(
                    'Baris produk %s wajib mengisi alasan retur.',
                    $saleItem->product->sku,
                ));
            }

            $alreadyReturned = $this->quantityAlreadyReturned((string) $saleItem->id, (string) $saleReturn->id);
            $remaining = bcsub((string) $saleItem->quantity, $alreadyReturned, 4);

            if (bccomp((string) $line->quantity, $remaining, 4) > 0) {
                throw new SaleValidationException(sprintf(
                    'Baris produk %s: kuantitas retur (%s) melebihi sisa yang belum diretur (%s).',
                    $saleItem->product->sku,
                    (string) $line->quantity,
                    $remaining,
                ));
            }

            $this->serialNumbers->validate($saleItem->product, (string) $line->quantity, $line->serial_numbers);
        }
    }

    /**
     * Total kuantitas yang SUDAH diretur (dokumen `SaleReturn` FINAL lain,
     * TIDAK termasuk dokumen ini sendiri) untuk baris penjualan tertentu.
     * `whereHas` di-scope eksplisit tanpa bergantung `BranchContext` aktor
     * (yang bisa berbeda dari cabang dokumen ini, mis. Owner meninjau lintas
     * cabang) — sama alasan `withoutGlobalScope` pada
     * `FinalizeStockAdjustmentAction::monthlyTotalForActor()`.
     */
    private function quantityAlreadyReturned(string $saleItemId, string $excludingSaleReturnId): string
    {
        $rows = SaleReturnLine::query()
            ->where('sale_item_id', $saleItemId)
            ->where('sale_return_id', '!=', $excludingSaleReturnId)
            ->whereHas('saleReturn', fn ($query) => $query
                ->withoutGlobalScope(BranchScope::class)
                ->where('state', DocumentState::Final))
            ->get(['quantity']);

        $total = '0';

        foreach ($rows as $row) {
            $total = bcadd($total, (string) $row->quantity, 4);
        }

        return $total;
    }
}
