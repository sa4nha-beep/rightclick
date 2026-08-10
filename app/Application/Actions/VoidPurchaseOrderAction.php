<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Void purchase order final (T5.1, R4). Berbeda dari
 * `VoidStockAdjustmentAction`/`VoidSaleAction` — TIDAK memanggil
 * `StockLedgerService` sama sekali, karena PO tidak pernah menyentuh ledger
 * stok (itu urusan goods receipt, T5.2). Void PO murni transisi status
 * dokumen.
 */
final class VoidPurchaseOrderAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $reason) {
            $this->documentStates->void($purchaseOrder, $reason);

            return $purchaseOrder->fresh();
        });
    }
}
