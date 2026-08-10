<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Void faktur pembelian final (T5.2, R4). TIDAK memanggil
 * `StockLedgerService` — faktur tidak pernah menyentuh ledger stok (itu
 * urusan `GoodsReceipt`). Void faktur murni koreksi sisi finansial/AP
 * (mis. nomor faktur salah input) tanpa memengaruhi stok yang sudah
 * diterima. Tidak ada dependensi dokumen lain yang perlu diperiksa — beda
 * dari `VoidGoodsReceiptAction`.
 */
final class VoidPurchaseInvoiceAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
    ) {}

    public function execute(PurchaseInvoice $purchaseInvoice, string $reason): PurchaseInvoice
    {
        return DB::transaction(function () use ($purchaseInvoice, $reason) {
            $this->documentStates->void($purchaseInvoice, $reason);

            return $purchaseInvoice->fresh();
        });
    }
}
