<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\StockConsumption;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Models\StockMutation;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Satu-satunya penulis `stock_mutations` (R1) — simpul kritis T3.2.
 * Ditegakkan `tests/Arch/StockMutationSingleWriterTest.php`: tidak ada
 * kode lain di `app/` yang boleh memanggil `StockMutation::create()`.
 *
 * Kontrak penguncian (CLAUDE.md §7, sama urutannya di setiap pemanggil):
 *  1. `DocumentNumberService::next()` mengunci `document_sequences` LEBIH DULU
 *     — tanggung jawab pemanggil (action dokumen), bukan layanan ini.
 *  2. `consume()` mengunci `stock_batches` (`qty_remaining > 0 ORDER BY
 *     received_at ASC FOR UPDATE`) — FIFO, R2/AC-08.
 *  3. `stock_balances` dikunci baris-per-baris SETELAH batch, lewat
 *     `applyBalanceDelta()` — konsisten dengan pola `insertOrIgnore` +
 *     `lockForUpdate` yang sudah dipakai `DocumentNumberService`.
 *
 * Seluruh pemanggilan WAJIB berada di dalam transaksi milik dokumen
 * (`assertInsideTransaction()`) — sama seperti `DocumentNumberService`.
 *
 * `outbox_events` (T5.7) belum ada di Fase 3 — layanan ini berhenti setelah
 * menulis mutasi/balance, tidak menyentuh sinkronisasi sama sekali.
 *
 * Serial number (R3, T3.7) BELUM ditegakkan di sini — parameter akan
 * ditambahkan T3.7 begitu kolom `stock_mutations.serial_numbers` ada.
 */
final class StockLedgerService
{
    /**
     * Terbitkan batch baru dan mutasi penambahan (R2 — `unitCost` TERMASUK
     * PPN). Dipakai untuk penerimaan opname/opening balance (T3.4),
     * penyesuaian naik (T3.5), penerimaan transfer (T3.6), kelak goods
     * receipt (T5.2).
     */
    public function receive(
        Branch $branch,
        Product $product,
        string $quantity,
        string $unitCost,
        CarbonInterface $receivedAt,
        Model $reference,
        StockMutationType $mutationType,
    ): StockBatch {
        $this->assertInsideTransaction();
        $this->assertPositive($quantity, 'Kuantitas penerimaan');
        $this->assertPositive($unitCost, 'Unit cost');

        $batch = StockBatch::create([
            'branch_id' => $branch->getKey(),
            'product_id' => $product->getKey(),
            'received_at' => $receivedAt,
            'unit_cost' => $unitCost,
            'qty_received' => $quantity,
            'qty_remaining' => $quantity,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
        ]);

        $this->recordMutation($branch, $product, $batch, $mutationType, $quantity, $unitCost, $reference);
        $this->applyBalanceDelta($branch, $product, $quantity);

        return $batch;
    }

    /**
     * Konsumsi FIFO (R2/AC-08) sejumlah `$quantity` dari batch tersedia,
     * mengunci baris yang relevan sampai transaksi caller commit (R7 — stok
     * tidak pernah boleh negatif; AC-10 — dua kasir menjual unit terakhir
     * bersamaan, satu berhasil satu ditolak). Melempar
     * `InsufficientStockException` (dan mem-batalkan seluruh transaksi
     * dokumen lewat rollback) bila stok tersedia kurang dari yang diminta.
     *
     * @return Collection<int, StockConsumption> Rincian batch yang terpakai,
     *                                           berurutan sesuai FIFO —
     *                                           dibutuhkan T3.6 (transfer)
     *                                           untuk membangun batch
     *                                           tujuan dengan biaya yang
     *                                           benar.
     */
    public function consume(
        Branch $branch,
        Product $product,
        string $quantity,
        Model $reference,
        StockMutationType $mutationType,
    ): Collection {
        $this->assertInsideTransaction();
        $this->assertPositive($quantity, 'Kuantitas konsumsi');

        $remaining = $quantity;
        $consumptions = new Collection;

        // Tiebreaker berlapis (R2/AC-08): `received_at` adalah tanggal
        // bisnis (bisa diisi mundur, mis. opname yang mencatat tanggal
        // fisik barang datang) sehingga tetap kunci urutan utama. Dua batch
        // bisa punya `received_at` IDENTIK — bahkan `created_at` bisa ikut
        // seri pada lingkungan dengan resolusi jam kasar (virtualisasi,
        // beberapa panggilan dalam permintaan yang sama). `id` (UUID v7,
        // `HasUuidV7`) adalah tiebreaker terakhir yang PASTI unik dan pasti
        // terurut sesuai waktu pembuatan menurut spesifikasi UUIDv7 —
        // tanpanya, urutan FIFO pada baris yang benar-benar seri tidak
        // deterministik karena Postgres tidak menjamin urutan stabil untuk
        // ORDER BY dengan nilai yang sama persis di semua kolom pengurut.
        $batches = StockBatch::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branch->getKey())
            ->where('product_id', $product->getKey())
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            $available = (string) $batch->qty_remaining;
            $take = bccomp($available, $remaining, 4) < 0 ? $available : $remaining;

            $batch->setAttribute('qty_remaining', bcsub($available, $take, 4));
            $batch->save();

            $unitCost = (string) $batch->unit_cost;

            $this->recordMutation(
                $branch,
                $product,
                $batch,
                $mutationType,
                bcmul($take, '-1', 4),
                $unitCost,
                $reference,
            );

            $consumptions->push(new StockConsumption($batch->getKey(), $take, $unitCost));

            $remaining = bcsub($remaining, $take, 4);
        }

        if (bccomp($remaining, '0', 4) > 0) {
            throw new InsufficientStockException(sprintf(
                'Stok %s di cabang %s tidak mencukupi (R7): diminta %s, kurang %s.',
                $product->name,
                $branch->code,
                $quantity,
                $remaining,
            ));
        }

        $this->applyBalanceDelta($branch, $product, bcmul($quantity, '-1', 4));

        return $consumptions;
    }

    /**
     * Balikkan seluruh mutasi yang merujuk `$originalReference` — dipanggil
     * saat dokumen (opname/adjustment/transfer) di-void. TIDAK menghapus
     * atau mengubah baris lama (§16 peringatan #9): menerbitkan mutasi baru
     * berarah berlawanan yang merujuk `$voidReference`.
     *
     * Konsumsi (mutasi negatif) dibalik dengan mengembalikan `qty_remaining`
     * batch asalnya. Penerimaan (mutasi positif) hanya dapat dibalik bila
     * batch itu belum tersentuh sejak diterbitkan (`qty_remaining` masih
     * sama dengan `qty_received`) — bila sudah terkonsumsi sebagian oleh
     * dokumen lain, melempar exception agar dokumen yang mengonsumsinya
     * dibatalkan lebih dulu.
     */
    public function reverseForReference(Model $originalReference, Model $voidReference): void
    {
        $this->assertInsideTransaction();

        $originalMutations = StockMutation::withoutGlobalScope(BranchScope::class)
            ->where('reference_type', $originalReference->getMorphClass())
            ->where('reference_id', $originalReference->getKey())
            ->get();

        foreach ($originalMutations as $mutation) {
            $batch = StockBatch::withoutGlobalScope(BranchScope::class)
                ->where('id', $mutation->stock_batch_id)
                ->lockForUpdate()
                ->firstOrFail();

            $branch = Branch::findOrFail($mutation->branch_id);
            $product = Product::findOrFail($mutation->product_id);
            $unitCost = (string) $mutation->unit_cost;

            if (bccomp((string) $mutation->quantity, '0', 4) < 0) {
                $restoreQty = bcmul((string) $mutation->quantity, '-1', 4);

                $batch->setAttribute('qty_remaining', bcadd((string) $batch->qty_remaining, $restoreQty, 4));
                $batch->save();

                $this->recordMutation($branch, $product, $batch, StockMutationType::VoidReversal, $restoreQty, $unitCost, $voidReference);
                $this->applyBalanceDelta($branch, $product, $restoreQty);

                continue;
            }

            if (bccomp((string) $batch->qty_remaining, (string) $batch->qty_received, 4) !== 0) {
                throw new InsufficientStockException(sprintf(
                    'Tidak dapat membalik penerimaan batch %s — sudah terpakai sebagian '.
                    '(qty_remaining %s ≠ qty_received %s). Batalkan dokumen yang '.
                    'mengonsumsinya terlebih dahulu.',
                    $batch->getKey(),
                    $batch->qty_remaining,
                    $batch->qty_received,
                ));
            }

            $drainQty = (string) $mutation->quantity;

            $batch->setAttribute('qty_remaining', '0.0000');
            $batch->save();

            $this->recordMutation($branch, $product, $batch, StockMutationType::VoidReversal, bcmul($drainQty, '-1', 4), $unitCost, $voidReference);
            $this->applyBalanceDelta($branch, $product, bcmul($drainQty, '-1', 4));
        }
    }

    /**
     * Kuantitas tersedia saat ini. Membaca cache `stock_balances`; bila
     * baris belum ada (belum pernah ada mutasi untuk kombinasi ini),
     * jatuh ke penjumlahan langsung atas `stock_batches`.
     */
    public function availableQuantity(Branch $branch, Product $product): string
    {
        $qty = DB::table('stock_balances')
            ->where('branch_id', $branch->getKey())
            ->where('product_id', $product->getKey())
            ->value('qty_on_hand');

        if ($qty !== null) {
            return (string) $qty;
        }

        $sum = StockBatch::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branch->getKey())
            ->where('product_id', $product->getKey())
            ->sum('qty_remaining');

        // sum() bawaan Eloquent mengembalikan int 0 (bukan '0.0000') bila
        // tidak ada baris cocok — normalkan skala 4 digit agar konsisten
        // dengan jalur `stock_balances` di atas (selalu string decimal:4).
        return bcadd((string) $sum, '0', 4);
    }

    private function recordMutation(
        Branch $branch,
        Product $product,
        StockBatch $batch,
        StockMutationType $mutationType,
        string $signedQuantity,
        string $unitCost,
        Model $reference,
    ): StockMutation {
        return StockMutation::create([
            'branch_id' => $branch->getKey(),
            'product_id' => $product->getKey(),
            'stock_batch_id' => $batch->getKey(),
            'mutation_type' => $mutationType,
            'quantity' => $signedQuantity,
            'unit_cost' => $unitCost,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'occurred_at' => now(),
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);
    }

    /**
     * Naikkan/turunkan `stock_balances.qty_on_hand` secara atomik. Pola
     * `insertOrIgnore` + `lockForUpdate` sama persis dengan
     * `DocumentNumberService::next()` pada `document_sequences` — baris
     * dijamin ada tanpa balapan, lalu dikunci untuk sisa transaksi.
     */
    private function applyBalanceDelta(Branch $branch, Product $product, string $delta): void
    {
        DB::table('stock_balances')->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'branch_id' => $branch->getKey(),
            'product_id' => $product->getKey(),
            'qty_on_hand' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $current = DB::table('stock_balances')
            ->where('branch_id', $branch->getKey())
            ->where('product_id', $product->getKey())
            ->lockForUpdate()
            ->value('qty_on_hand');

        DB::table('stock_balances')
            ->where('branch_id', $branch->getKey())
            ->where('product_id', $product->getKey())
            ->update([
                'qty_on_hand' => bcadd((string) $current, $delta, 4),
                'updated_at' => now(),
            ]);
    }

    private function assertPositive(string $value, string $label): void
    {
        if (bccomp($value, '0', 4) <= 0) {
            throw new InvalidArgumentException("{$label} harus lebih besar dari nol, diberikan: {$value}.");
        }
    }

    private function assertInsideTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(
                'StockLedgerService harus dipanggil di dalam transaksi dokumen (R6/R7) — '
                .'penguncian FOR UPDATE hanya bermakna selama transaksi berjalan, sama seperti '
                .'DocumentNumberService::next().',
            );
        }
    }
}
