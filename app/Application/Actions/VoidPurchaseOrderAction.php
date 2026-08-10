<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Void purchase order final (T5.1, R4). Berbeda dari
 * `VoidStockAdjustmentAction`/`VoidSaleAction` — TIDAK memanggil
 * `StockLedgerService` sama sekali, karena PO tidak pernah menyentuh ledger
 * stok (itu urusan goods receipt, T5.2). Void PO murni transisi status
 * dokumen.
 *
 * `OutboxService` (T5.7 retrofit, simpul kritis): `purchase_order.voided`.
 */
final class VoidPurchaseOrderAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $reason) {
            $this->documentStates->void($purchaseOrder, $reason);
            $this->outbox->record($purchaseOrder->branch, $purchaseOrder, 'purchase_order.voided');

            return $purchaseOrder->fresh();
        });
    }
}
