<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\ApprovalService;
use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Domain\Procurement\Exceptions\PurchaseOrderValidationException;
use App\Domain\Shared\Enums\DocumentType;
use App\Domain\Shared\Enums\PartnerType;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use App\Infrastructure\Persistence\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Finalisasi purchase order (T5.1).
 *
 * TH4 (CLAUDE.md §10, `po.max_admin`) ditegakkan di sini, bukan Policy —
 * sama alasan `FinalizeStockAdjustmentAction`/`FinalizeSaleAction`: nilai
 * total baru diketahui setelah baris dibaca, bukan sesuatu yang bisa
 * digerbang permission tetap. Owner DIKECUALIKAN — §10 membingkai TH4
 * sebagai batas SEBELUM eskalasi ke Owner, sama pola TH1–TH3b.
 *
 * Alur AP-01: melebihi ambang TIDAK menolak transaksi — dokumen tetap
 * `draft` dengan `Approval` tertunda (`ApprovalService::request()`).
 * `ApprovePurchaseOrderAction` memanggil `applyAndFinalize()` setelah
 * disetujui, melewati pemeriksaan ambang.
 *
 * TIDAK menyentuh `StockLedgerService` sama sekali — PO adalah rencana
 * pembelian, bukan penerimaan barang. Stok baru bergerak saat goods
 * receipt (T5.2).
 */
final class FinalizePurchaseOrderAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly ApprovalService $approvals,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->loadMissing(['branch', 'partner', 'lines.product']);

            $this->validateStructure($purchaseOrder);

            $actor = Auth::user();
            $actorId = $actor === null ? null : (string) $actor->id;

            if ($actor === null || ! $actor->hasRole('owner')) {
                $totalAmount = $this->calculateTotal($purchaseOrder);
                $threshold = (string) (Setting::get('po.max_admin') ?? '10000000');

                if (bccomp($totalAmount, $threshold, 2) > 0) {
                    $this->approvals->request($purchaseOrder, $actorId, $purchaseOrder->branch_id);

                    return $purchaseOrder->fresh(['lines']);
                }
            }

            return $this->applyAndFinalize($purchaseOrder);
        });
    }

    /**
     * Kunci total dan finalisasi — dipanggil `execute()` langsung bila di
     * bawah ambang, atau `ApprovePurchaseOrderAction` setelah Approval
     * disetujui.
     */
    public function applyAndFinalize(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $purchaseOrder->loadMissing(['branch', 'partner', 'lines.product']);

        $this->validateStructure($purchaseOrder);

        $purchaseOrder->total_amount = $this->calculateTotal($purchaseOrder);
        $purchaseOrder->document_number = $this->documentNumbers->next(DocumentType::PurchaseOrder, $purchaseOrder->branch);
        $purchaseOrder->save();

        $this->documentStates->finalize($purchaseOrder);

        $this->outbox->record($purchaseOrder->branch, $purchaseOrder, 'purchase_order.finalized');

        return $purchaseOrder->fresh(['lines']);
    }

    private function validateStructure(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->lines->isEmpty()) {
            throw new PurchaseOrderValidationException('Dokumen purchase order tanpa baris tidak dapat difinalisasi.');
        }

        if ($purchaseOrder->partner->partner_type === PartnerType::Customer) {
            throw new PurchaseOrderValidationException(sprintf(
                'Mitra %s bukan pemasok — tidak dapat menjadi tujuan purchase order.',
                $purchaseOrder->partner->name,
            ));
        }
    }

    private function calculateTotal(PurchaseOrder $purchaseOrder): string
    {
        $total = '0';

        foreach ($purchaseOrder->lines as $line) {
            $total = bcadd($total, (string) $line->line_total, 2);
        }

        return $total;
    }
}
