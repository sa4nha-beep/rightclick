<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Application\Services\SerialNumberValidationService;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Enums\StockOpnameType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Models\StockOpname;
use App\Infrastructure\Persistence\Models\StockOpnameLine;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;

/**
 * Finalisasi stock opname (T3.4). Bukan sekadar
 * `DocumentStateService::finalize()` — baris berselisih harus diproses
 * lewat `StockLedgerService` lebih dulu, dan AC-12 ("baris opname
 * berselisih tanpa alasan → penyimpanan ditolak") divalidasi di sini.
 *
 * Urutan (di dalam satu transaksi, mengikuti kontrak penguncian CLAUDE.md §7):
 *  1. Validasi SELURUH baris (alasan, unit_cost) SEBELUM mengambil nomor
 *     dokumen — permintaan yang pasti gagal tidak boleh membakar nomor.
 *  2. `DocumentNumberService::next()` — kunci `document_sequences`.
 *  3. Per baris berselisih: `StockLedgerService::receive()`/`consume()` —
 *     kunci `stock_batches`.
 *  4. `DocumentStateService::finalize()`.
 *
 * `system_qty` DIHITUNG ULANG dari `StockLedgerService::availableQuantity()`
 * tepat di sini, TIDAK dipercaya dari nilai yang tersimpan saat baris
 * dibuat sebagai draft — mencegah selisih dihitung dari data basi bila stok
 * berubah (transaksi lain) di antara draft dan finalisasi.
 */
final class FinalizeStockOpnameAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly SerialNumberValidationService $serialNumbers,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(StockOpname $opname): StockOpname
    {
        return DB::transaction(function () use ($opname) {
            $opname->loadMissing(['branch', 'lines.product']);
            $lines = $opname->lines;

            $variances = [];

            foreach ($lines as $line) {
                $systemQty = $this->stockLedger->availableQuantity($opname->branch, $line->product);
                $variance = bcsub((string) $line->counted_qty, $systemQty, 4);

                if (bccomp($variance, '0', 4) !== 0 && blank($line->reason)) {
                    throw new StockDocumentValidationException(sprintf(
                        'Baris produk %s (%s) berselisih %s tanpa alasan — penyimpanan ditolak (AC-12).',
                        $line->product->sku,
                        $line->product->name,
                        $variance,
                    ));
                }

                if (bccomp($variance, '0', 4) > 0) {
                    $unitCost = $this->resolveUnitCost($opname, $line);
                    // T3.7/R3 — serial hanya divalidasi di sisi naik: unit
                    // baru masuk ledger perlu identitas. Sisi turun (barang
                    // hilang) tidak butuh tahu serial persis yang mana —
                    // MVP sengaja tanpa registry lintas transaksi (§3).
                    $this->serialNumbers->validate($line->product, $variance, $line->serial_numbers);
                } else {
                    $unitCost = null;
                }

                $variances[$line->id] = ['systemQty' => $systemQty, 'variance' => $variance, 'unitCost' => $unitCost];
            }

            $documentNumber = $this->documentNumbers->next(DocumentType::Opname, $opname->branch);
            $opname->document_number = $documentNumber;
            $opname->save();

            foreach ($lines as $line) {
                ['systemQty' => $systemQty, 'variance' => $variance, 'unitCost' => $unitCost] = $variances[$line->id];

                $line->system_qty = $systemQty;
                $line->save();

                if (bccomp($variance, '0', 4) === 0) {
                    continue;
                }

                if (bccomp($variance, '0', 4) > 0) {
                    $this->stockLedger->receive(
                        $opname->branch,
                        $line->product,
                        $variance,
                        $unitCost,
                        now(),
                        $opname,
                        StockMutationType::OpnameCorrectionIncrease,
                    );
                } else {
                    $this->stockLedger->consume(
                        $opname->branch,
                        $line->product,
                        bcmul($variance, '-1', 4),
                        $opname,
                        StockMutationType::OpnameCorrectionDecrease,
                    );
                }
            }

            $this->documentStates->finalize($opname);

            $this->outbox->record($opname->branch, $opname, 'stock_opname.finalized', ['lines']);

            return $opname->fresh(['lines']);
        });
    }

    /**
     * `unit_cost` untuk baris berselisih naik. `opening_balance` (R9) SELALU
     * wajib diisi eksplisit pada baris — tidak pernah diwarisi dari batch
     * lama, sekalipun kebetulan ada (opname saldo awal berarti sistem
     * dianggap belum punya riwayat). Opname berkala boleh mewarisi biaya
     * batch terbaru bila baris tidak mengisi `unit_cost` sendiri.
     */
    private function resolveUnitCost(StockOpname $opname, StockOpnameLine $line): string
    {
        if (filled($line->unit_cost)) {
            return (string) $line->unit_cost;
        }

        if ($opname->type === StockOpnameType::OpeningBalance) {
            throw new StockDocumentValidationException(sprintf(
                'Baris produk %s pada opname saldo awal wajib mengisi unit cost (R9) — tidak ada batch sebelumnya untuk diwariskan.',
                $line->product->sku,
            ));
        }

        $latestBatch = StockBatch::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $opname->branch_id)
            ->where('product_id', $line->product_id)
            ->orderByDesc('received_at')
            ->first();

        if ($latestBatch === null) {
            throw new StockDocumentValidationException(sprintf(
                'Baris produk %s berselisih naik tanpa batch sebelumnya untuk diwariskan biayanya — isi unit cost pada baris ini.',
                $line->product->sku,
            ));
        }

        return (string) $latestBatch->unit_cost;
    }
}
