<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\ApprovalService;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

/**
 * Menyetujui penyesuaian stok yang tertunda karena melebihi TH3/TH3b
 * (PT15). Berbeda dari `ApprovalService::approve()` polos — setelah
 * `Approval` disetujui, aksi ini LANGSUNG memanggil
 * `FinalizeStockAdjustmentAction::applyAndFinalize()` di transaksi yang
 * sama supaya dokumen benar-benar diterapkan ke ledger, bukan sekadar
 * berubah status approval-nya sementara dokumen tetap draft selamanya.
 */
final class ApproveStockAdjustmentAction
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly FinalizeStockAdjustmentAction $finalizeAction,
    ) {}

    public function execute(StockAdjustment $adjustment): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment) {
            $approval = Approval::query()
                ->where('approvable_type', $adjustment->getMorphClass())
                ->where('approvable_id', $adjustment->getKey())
                ->where('status', ApprovalStatus::Pending)
                ->latest('requested_at')
                ->first();

            if ($approval === null) {
                throw new ApprovalException('Tidak ada permintaan approval tertunda untuk dokumen ini.');
            }

            $this->approvals->approve($approval);

            return $this->finalizeAction->applyAndFinalize($adjustment);
        });
    }
}
