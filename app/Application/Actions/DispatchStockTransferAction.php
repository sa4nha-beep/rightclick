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
use App\Infrastructure\Persistence\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

/**
 * Finalisasi dokumen KIRIM transfer (T3.6, R12/AC-11). Mengonsumsi stok
 * cabang asal via FIFO — dari titik ini barang "transit": sudah hilang
 * dari saldo cabang asal, belum ada di cabang tujuan (baru muncul saat
 * `ReceiveStockTransferAction`). Rincian batch yang terpakai disimpan ke
 * `stock_transfer_line_batches` agar penerimaan mewarisi biaya yang tepat
 * apa pun yang terjadi pada batch sumber setelahnya.
 */
final class DispatchStockTransferAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly SerialNumberValidationService $serialNumbers,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer->loadMissing(['branch', 'lines.product']);

            if ($transfer->lines->isEmpty()) {
                throw new StockDocumentValidationException('Dokumen transfer tanpa baris tidak dapat dikirim.');
            }

            // T3.7/R3 — setiap baris transfer selalu memindahkan unit
            // tertentu (beda dari opname/adjustment yang hanya
            // memvalidasi sisi naik) — barang yang berpindah cabang selalu
            // relevan diketahui identitasnya bila produk dilacak serial.
            foreach ($transfer->lines as $line) {
                $this->serialNumbers->validate($line->product, (string) $line->quantity, $line->serial_numbers);
            }

            $documentNumber = $this->documentNumbers->next(DocumentType::TransferDispatch, $transfer->branch);
            $transfer->document_number = $documentNumber;
            $transfer->save();

            foreach ($transfer->lines as $line) {
                $consumptions = $this->stockLedger->consume(
                    $transfer->branch,
                    $line->product,
                    (string) $line->quantity,
                    $transfer,
                    StockMutationType::TransferOut,
                );

                foreach ($consumptions as $consumption) {
                    $line->lineBatches()->create([
                        'source_stock_batch_id' => $consumption->stockBatchId,
                        'quantity' => $consumption->quantity,
                        'unit_cost' => $consumption->unitCost,
                    ]);
                }
            }

            $this->documentStates->finalize($transfer);

            $this->outbox->record($transfer->branch, $transfer, 'stock_transfer.finalized', ['lines']);

            return $transfer->fresh(['lines.lineBatches']);
        });
    }
}
