<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\StockTransfer;
use App\Infrastructure\Persistence\Models\StockTransferReceipt;
use Illuminate\Support\Facades\DB;

/**
 * Membuat dan memfinalisasi dokumen TERIMA transfer (T3.6, R12/AC-11).
 * Hanya bisa dijalankan bila dokumen kirim sudah `final` dan belum punya
 * receipt (`unique(stock_transfer_id)` + pengecekan eksplisit di sini
 * untuk pesan error yang jelas).
 *
 * Membuat SATU batch baru di cabang tujuan PER baris
 * `stock_transfer_line_batches` — bila dispatch mengonsumsi dari beberapa
 * batch sumber (harga berbeda), tujuan mewarisi rincian yang sama persis,
 * bukan digabung jadi satu biaya rata-rata (R2 — jejak HPP per batch).
 */
final class ReceiveStockTransferAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(StockTransfer $transfer): StockTransferReceipt
    {
        return DB::transaction(function () use ($transfer) {
            if ($transfer->state !== DocumentState::Final) {
                throw new StockDocumentValidationException(
                    'Dokumen transfer belum dikirim (final) — tidak dapat diterima.',
                );
            }

            if ($transfer->receipt()->exists()) {
                throw new StockDocumentValidationException(
                    'Dokumen transfer ini sudah punya dokumen terima.',
                );
            }

            $transfer->loadMissing(['destBranch', 'lines.product', 'lines.lineBatches']);

            $receipt = StockTransferReceipt::create([
                'branch_id' => $transfer->dest_branch_id,
                'stock_transfer_id' => $transfer->getKey(),
            ]);

            $documentNumber = $this->documentNumbers->next(DocumentType::TransferReceipt, $transfer->destBranch);
            $receipt->document_number = $documentNumber;
            $receipt->save();

            foreach ($transfer->lines as $line) {
                foreach ($line->lineBatches as $lineBatch) {
                    $this->stockLedger->receive(
                        $transfer->destBranch,
                        $line->product,
                        (string) $lineBatch->quantity,
                        (string) $lineBatch->unit_cost,
                        now(),
                        $receipt,
                        StockMutationType::TransferIn,
                    );
                }
            }

            $this->documentStates->finalize($receipt);

            $this->outbox->record($transfer->destBranch, $receipt, 'stock_transfer_receipt.finalized');

            return $receipt->fresh();
        });
    }
}
