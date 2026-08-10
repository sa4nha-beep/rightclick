<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Application\Services\StockLedgerService;
use App\Infrastructure\Persistence\Models\StockTransferReceipt;
use Illuminate\Support\Facades\DB;

/**
 * Void dokumen TERIMA transfer (T3.6). Membalik batch yang terbentuk di
 * cabang tujuan — setelah ini, dokumen kirim (`StockTransfer`) baru bisa
 * ikut dibatalkan (`VoidStockTransferAction`).
 */
final class VoidStockTransferReceiptAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(StockTransferReceipt $receipt, string $reason): StockTransferReceipt
    {
        return DB::transaction(function () use ($receipt, $reason) {
            $this->stockLedger->reverseForReference($receipt, $receipt);
            $this->documentStates->void($receipt, $reason);
            $this->outbox->record($receipt->branch, $receipt, 'stock_transfer_receipt.voided');

            return $receipt->fresh();
        });
    }
}
