<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

/**
 * Void dokumen KIRIM transfer (T3.6). Ditolak bila masih ada
 * `stock_transfer_receipts` aktif (belum void) — voidkan receipt lebih
 * dulu, baru dispatch. Tanpa urutan ini, cabang tujuan bisa tetap memegang
 * batch hasil terima SEKALIGUS cabang asal mendapat kembali stoknya —
 * barang tergandakan di dua cabang sekaligus.
 */
final class VoidStockTransferAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
    ) {}

    public function execute(StockTransfer $transfer, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $reason) {
            $receipt = $transfer->receipt;

            if ($receipt !== null && $receipt->state !== DocumentState::Void) {
                throw new StockDocumentValidationException(
                    'Dokumen transfer ini sudah diterima — batalkan dokumen terima lebih dulu sebelum membatalkan pengiriman.',
                );
            }

            $this->stockLedger->reverseForReference($transfer, $transfer);
            $this->documentStates->void($transfer, $reason);

            return $transfer->fresh();
        });
    }
}
