<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\ApprovalService;
use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Application\Services\SerialNumberValidationService;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockAdjustmentDirection;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\Setting;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Models\StockMutation;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Finalisasi penyesuaian stok (T3.5). TH3/TH3b (CLAUDE.md §10) ditegakkan
 * di sini, bukan Policy — nilai dokumen baru diketahui setelah baris
 * dibaca, bukan sesuatu yang bisa digerbang permission tetap.
 *
 * Owner DIKECUALIKAN dari TH3/TH3b: tabel ambang §10 membingkai TH3/TH3b
 * sebagai batas untuk Admin/Gudang SEBELUM eskalasi ke Owner ("tanpa
 * approval Owner") — Owner adalah puncak rantai persetujuan itu sendiri,
 * bukan tingkatan lain yang perlu mengajukan ke dirinya sendiri. Ini
 * pembacaan struktural §10, BUKAN pola yang sudah ada di modul lain
 * (belum ada modul lain yang menegakkan TH1/TH2 saat ini).
 *
 * Alur AP-01: melebihi ambang TIDAK menolak transaksi — dokumen tetap
 * `draft` dengan `Approval` tertunda (`ApprovalService::request()`).
 * `ApproveStockAdjustmentAction` memanggil `applyAndFinalize()` setelah
 * disetujui, melewati pemeriksaan ambang (sudah lolos approval manusia).
 */
final class FinalizeStockAdjustmentAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly ApprovalService $approvals,
        private readonly SerialNumberValidationService $serialNumbers,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(StockAdjustment $adjustment): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment) {
            $adjustment->loadMissing(['branch', 'lines.product']);

            $this->validateLines($adjustment);

            $actor = Auth::user();
            $actorId = $actor === null ? null : (string) $actor->id;

            if ($actor === null || ! $actor->hasRole('owner')) {
                $estimatedValue = $this->estimateValue($adjustment);
                $monthlyTotal = $this->monthlyTotalForActor($adjustment->branch_id, $actorId);
                $projectedMonthlyTotal = bcadd($monthlyTotal, $estimatedValue, 2);

                $th3 = (string) (Setting::get('adjustment.max_admin') ?? '5000000');
                $th3b = (string) (Setting::get('adjustment.max_admin_monthly') ?? '15000000');

                if (bccomp($estimatedValue, $th3, 2) > 0 || bccomp($projectedMonthlyTotal, $th3b, 2) > 0) {
                    $this->approvals->request($adjustment, $actorId, $adjustment->branch_id);

                    return $adjustment->fresh(['lines']);
                }
            }

            return $this->applyAndFinalize($adjustment);
        });
    }

    /**
     * Terapkan ke ledger dan finalisasi — dipanggil `execute()` langsung
     * bila di bawah ambang, atau `ApproveStockAdjustmentAction` setelah
     * Approval disetujui.
     */
    public function applyAndFinalize(StockAdjustment $adjustment): StockAdjustment
    {
        $adjustment->loadMissing(['branch', 'lines.product']);

        $documentNumber = $this->documentNumbers->next(DocumentType::Adjustment, $adjustment->branch);
        $adjustment->document_number = $documentNumber;
        $adjustment->save();

        foreach ($adjustment->lines as $line) {
            if ($line->direction === StockAdjustmentDirection::Increase) {
                $this->stockLedger->receive(
                    $adjustment->branch,
                    $line->product,
                    (string) $line->quantity,
                    (string) $line->unit_cost,
                    now(),
                    $adjustment,
                    StockMutationType::AdjustmentIncrease,
                );
            } else {
                $this->stockLedger->consume(
                    $adjustment->branch,
                    $line->product,
                    (string) $line->quantity,
                    $adjustment,
                    StockMutationType::AdjustmentDecrease,
                );
            }
        }

        $this->documentStates->finalize($adjustment);

        $this->outbox->record($adjustment->branch, $adjustment, 'stock_adjustment.finalized');

        return $adjustment->fresh(['lines']);
    }

    private function validateLines(StockAdjustment $adjustment): void
    {
        if ($adjustment->lines->isEmpty()) {
            throw new StockDocumentValidationException('Dokumen penyesuaian tanpa baris tidak dapat difinalisasi.');
        }

        foreach ($adjustment->lines as $line) {
            if (blank(trim((string) $line->reason))) {
                throw new StockDocumentValidationException(sprintf(
                    'Baris produk %s wajib mengisi alasan penyesuaian.',
                    $line->product->sku,
                ));
            }

            if ($line->direction === StockAdjustmentDirection::Increase) {
                if (blank($line->unit_cost)) {
                    throw new StockDocumentValidationException(sprintf(
                        'Baris produk %s (naik) wajib mengisi unit cost.',
                        $line->product->sku,
                    ));
                }

                // T3.7/R3 — sama pola dengan opname: hanya sisi naik yang
                // divalidasi (unit baru masuk ledger perlu identitas).
                $this->serialNumbers->validate($line->product, (string) $line->quantity, $line->serial_numbers);
            }
        }
    }

    /**
     * Estimasi nilai absolut dokumen untuk pemeriksaan TH3/TH3b. Baris naik
     * memakai `unit_cost` eksplisit (sudah pasti, divalidasi wajib ada).
     * Baris turun memakai rata-rata tertimbang batch tersedia — perkiraan
     * yang disengaja disederhanakan (bukan menjalankan FIFO penuh dulu lalu
     * rollback bila ternyata melebihi ambang); biaya sebenarnya yang
     * tercatat di `stock_mutations` tetap presisi FIFO begitu benar-benar
     * diterapkan lewat `applyAndFinalize()`.
     */
    private function estimateValue(StockAdjustment $adjustment): string
    {
        $total = '0';

        foreach ($adjustment->lines as $line) {
            $unitCost = $line->direction === StockAdjustmentDirection::Increase
                ? (string) $line->unit_cost
                : $this->weightedAverageCost($adjustment->branch_id, $line->product_id);

            $total = bcadd($total, bcmul((string) $line->quantity, $unitCost, 2), 2);
        }

        return $total;
    }

    private function weightedAverageCost(string $branchId, string $productId): string
    {
        $batches = StockBatch::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', 0)
            ->get(['qty_remaining', 'unit_cost']);

        $totalQty = '0';
        $totalValue = '0';

        foreach ($batches as $batch) {
            $totalQty = bcadd($totalQty, (string) $batch->qty_remaining, 4);
            $totalValue = bcadd($totalValue, bcmul((string) $batch->qty_remaining, (string) $batch->unit_cost, 2), 2);
        }

        if (bccomp($totalQty, '0', 4) === 0) {
            return '0';
        }

        return bcdiv($totalValue, $totalQty, 2);
    }

    /**
     * Total nilai absolut penyesuaian yang SUDAH diterapkan (bukan yang
     * masih menunggu approval) bulan ini oleh aktor ini di cabang ini —
     * dihitung dari `stock_mutations` (ledger sebenarnya, bukan estimasi)
     * atas dokumen `StockAdjustment` berstatus `final` (dokumen `void`
     * sengaja tidak ikut terhitung — koreksinya sudah dibatalkan).
     */
    private function monthlyTotalForActor(string $branchId, ?string $actorId): string
    {
        if ($actorId === null) {
            return '0';
        }

        $timezone = config('rightclick.display_timezone');
        $start = now()->setTimezone($timezone)->startOfMonth();
        $end = now()->setTimezone($timezone)->endOfMonth();

        $adjustmentIds = StockAdjustment::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->where('created_by', $actorId)
            ->where('state', DocumentState::Final)
            ->whereBetween('finalized_at', [$start, $end])
            ->pluck('id');

        if ($adjustmentIds->isEmpty()) {
            return '0';
        }

        $rows = StockMutation::withoutGlobalScope(BranchScope::class)
            ->where('reference_type', (new StockAdjustment)->getMorphClass())
            ->whereIn('reference_id', $adjustmentIds)
            ->get(['quantity', 'unit_cost']);

        $total = '0';

        foreach ($rows as $row) {
            $quantity = (string) $row->quantity;
            $absoluteQuantity = str_starts_with($quantity, '-') ? substr($quantity, 1) : $quantity;
            $total = bcadd($total, bcmul($absoluteQuantity, (string) $row->unit_cost, 2), 2);
        }

        return $total;
    }
}
