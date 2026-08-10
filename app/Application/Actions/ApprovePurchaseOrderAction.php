<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\ApprovalService;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Menyetujui purchase order yang tertunda karena melebihi TH4. Sama pola
 * dengan `ApproveStockAdjustmentAction`/`ApproveSaleDiscountAction` —
 * setelah `Approval` disetujui, aksi ini LANGSUNG memanggil
 * `FinalizePurchaseOrderAction::applyAndFinalize()` di transaksi yang sama
 * supaya dokumen benar-benar difinalisasi, bukan sekadar berubah status
 * approval-nya sementara dokumen tetap draft selamanya.
 */
final class ApprovePurchaseOrderAction
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly FinalizePurchaseOrderAction $finalizeAction,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $approval = Approval::query()
                ->where('approvable_type', $purchaseOrder->getMorphClass())
                ->where('approvable_id', $purchaseOrder->getKey())
                ->where('status', ApprovalStatus::Pending)
                ->latest('requested_at')
                ->first();

            if ($approval === null) {
                throw new ApprovalException('Tidak ada permintaan approval tertunda untuk dokumen ini.');
            }

            $this->approvals->approve($approval);

            return $this->finalizeAction->applyAndFinalize($purchaseOrder);
        });
    }
}
