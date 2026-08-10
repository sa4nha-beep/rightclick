<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\ApprovalService;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Menyetujui penjualan yang tertunda karena diskon melebihi TH1/TH2 (T4.2).
 * Sama pola dengan `ApproveStockAdjustmentAction` — setelah `Approval`
 * disetujui, aksi ini LANGSUNG memanggil `FinalizeSaleAction::applyAndFinalize()`
 * di transaksi yang sama supaya dokumen benar-benar diterapkan, bukan
 * sekadar berubah status approval-nya sementara dokumen tetap draft.
 */
final class ApproveSaleDiscountAction
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly FinalizeSaleAction $finalizeAction,
    ) {}

    public function execute(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $approval = Approval::query()
                ->where('approvable_type', $sale->getMorphClass())
                ->where('approvable_id', $sale->getKey())
                ->where('status', ApprovalStatus::Pending)
                ->latest('requested_at')
                ->first();

            if ($approval === null) {
                throw new ApprovalException('Tidak ada permintaan approval tertunda untuk penjualan ini.');
            }

            $this->approvals->approve($approval);

            return $this->finalizeAction->applyAndFinalize($sale);
        });
    }
}
