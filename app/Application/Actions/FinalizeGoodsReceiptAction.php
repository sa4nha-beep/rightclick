<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Application\Services\SerialNumberValidationService;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use Illuminate\Support\Facades\DB;

/**
 * Finalisasi penerimaan barang (T5.2, SIMPUL KRITIS). Dokumen inilah yang
 * MEMANGGIL `StockLedgerService::receive()` — BUKAN `purchase_invoices`.
 * `unit_cost` per baris WAJIB SUDAH TERMASUK PPN (R2/AC-09) saat baris
 * ditulis — diambil apa adanya dari nilai faktur pemasok yang menyertai
 * barang secara fisik (HAEN KOMPUTER non-PKP, PPN tidak dapat dikreditkan,
 * jadi tidak ada pemisahan DPP yang perlu dilakukan di sini sama sekali).
 *
 * TIDAK ADA alur ambang/`ApprovalService` — CLAUDE.md §10 tidak menetapkan
 * TH untuk goods receipt (beda dari `FinalizePurchaseOrderAction`/TH4,
 * `FinalizeStockAdjustmentAction`/TH3). Finalisasi murni digerbang
 * `GoodsReceiptPolicy::finalize()` (`perform_goods_receipt`).
 *
 * Serial number (R3/T3.7): sisi "naik", divalidasi wajib untuk produk
 * serial — pola sama `FinalizeStockAdjustmentAction`/`FinalizeStockOpnameAction`.
 */
final class FinalizeGoodsReceiptAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly SerialNumberValidationService $serialNumbers,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(GoodsReceipt $goodsReceipt): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceipt) {
            $goodsReceipt->loadMissing(['branch', 'lines.product']);

            $this->validateLines($goodsReceipt);

            $totalAmount = '0';
            $goodsReceipt->document_number = $this->documentNumbers->next(DocumentType::GoodsReceipt, $goodsReceipt->branch);

            foreach ($goodsReceipt->lines as $line) {
                $this->stockLedger->receive(
                    $goodsReceipt->branch,
                    $line->product,
                    (string) $line->quantity,
                    (string) $line->unit_cost,
                    now(),
                    $goodsReceipt,
                    StockMutationType::Receipt,
                );

                $totalAmount = bcadd($totalAmount, (string) $line->line_total, 2);
            }

            $goodsReceipt->total_amount = $totalAmount;
            $goodsReceipt->save();

            $this->documentStates->finalize($goodsReceipt);

            $this->outbox->record($goodsReceipt->branch, $goodsReceipt, 'goods_receipt.finalized');

            return $goodsReceipt->fresh(['lines']);
        });
    }

    private function validateLines(GoodsReceipt $goodsReceipt): void
    {
        if ($goodsReceipt->lines->isEmpty()) {
            throw new StockDocumentValidationException('Dokumen penerimaan barang tanpa baris tidak dapat difinalisasi.');
        }

        foreach ($goodsReceipt->lines as $line) {
            $this->serialNumbers->validate($line->product, (string) $line->quantity, $line->serial_numbers);
        }
    }
}
