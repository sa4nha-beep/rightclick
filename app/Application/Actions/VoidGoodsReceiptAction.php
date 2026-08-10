<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use Illuminate\Support\Facades\DB;

/**
 * Void penerimaan barang final (T5.2, R4). Ditolak bila masih ada
 * `purchase_invoices` aktif (belum void) yang menaut ke dokumen ini —
 * batalkan faktur lebih dulu, baru penerimaannya. Tanpa urutan ini, faktur
 * bisa tetap mengklaim hutang atas stok yang sudah dibalik dari ledger —
 * pola sama `VoidStockTransferAction` (dispatch vs receipt).
 */
final class VoidGoodsReceiptAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
    ) {}

    public function execute(GoodsReceipt $goodsReceipt, string $reason): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceipt, $reason) {
            $invoice = $goodsReceipt->purchaseInvoice;

            if ($invoice !== null && $invoice->state !== DocumentState::Void) {
                throw new StockDocumentValidationException(
                    'Penerimaan ini sudah memiliki faktur pembelian — batalkan faktur lebih dulu sebelum membatalkan penerimaan.',
                );
            }

            $this->stockLedger->reverseForReference($goodsReceipt, $goodsReceipt);
            $this->documentStates->void($goodsReceipt, $reason);

            return $goodsReceipt->fresh();
        });
    }
}
