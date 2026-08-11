<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Infrastructure\Persistence\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * T3.11 — uji beban `StockLedgerService` (CLAUDE.md §13: "20.000 SKU,
 * 500.000 mutasi, pada i3-7100"). Cakupan disepakati eksplisit dengan
 * pengguna (AskUserQuestion) — SEMPIT pada throughput `receive()`/
 * `consume()`, satu-satunya penulis `stock_mutations` (R1) yang jadi
 * bottleneck struktural di SETIAP alur finalisasi Sale/GoodsReceipt/
 * StockAdjustment/StockOpname/StockTransfer. Rebuild-balances/Filament
 * index/POS catalog TIDAK dicakup di sini — hanya throughput ledger yang
 * dipilih pengguna.
 *
 * PERINGATAN LINGKUNGAN (dicatat eksplisit, bukan diam-diam diabaikan):
 * dijalankan di container Docker dev, BUKAN hardware i3-7100 (CLAUDE.md
 * §14) yang jadi target produksi sesungguhnya. Angka absolut (ms/operasi)
 * TIDAK BOLEH dipakai langsung untuk keputusan kapasitas produksi — WAJIB
 * diverifikasi ulang di hardware sungguhan sebelum go-live. Yang tetap
 * bernilai dari sini: bottleneck RELATIF (RECEIVE vs CONSUME, degradasi
 * seiring skala) dan bukti arsitektur (locking pesimistis FOR UPDATE,
 * single-writer) tidak runtuh pada volume ini.
 *
 * Desain beban: `$skus` Product (bulk factory, DI LUAR pengukuran —
 * bukan bagian ledger) → SATU `receive()` batch besar per produk (mengisi
 * `$skus` mutasi pertama) → sisa target `$mutations` diisi `consume()`
 * kecil acak lintas produk (masing-masing HANYA menyentuh SATU batch,
 * tidak memaksa FIFO menyeberang banyak batch — korektnas FIFO-crossing
 * sudah teruji `StockLedgerServiceTest`, perintah ini murni throughput).
 */
class StockLoadTestCommand extends Command
{
    protected $signature = 'stock:load-test
        {--skus=20000 : Jumlah Product yang diseed}
        {--mutations=500000 : Total baris stock_mutations target (termasuk fase RECEIVE)}
        {--chunk=1000 : Ukuran chunk saat bulk-insert Product}';

    protected $description = 'T3.11 — uji beban throughput StockLedgerService::receive()/consume()';

    public function handle(StockLedgerService $ledger): int
    {
        $skuCount = (int) $this->option('skus');
        $mutationTarget = (int) $this->option('mutations');
        $chunkSize = (int) $this->option('chunk');

        if ($mutationTarget < $skuCount) {
            $this->error('--mutations harus >= --skus (fase RECEIVE butuh satu mutasi per SKU).');

            return self::FAILURE;
        }

        $this->warn('Dijalankan di container Docker dev — angka BUKAN representasi hardware i3-7100 produksi. Lihat docblock berkas ini.');

        // `branches.code` dibatasi varchar(10) — pola LT + 8 karakter acak
        // tetap unik tanpa melanggar constraint.
        $branch = Branch::create([
            'code' => 'LT'.Str::upper(Str::random(8)),
            'name' => 'Cabang Uji Beban T3.11',
            'is_hq' => false,
            'is_active' => true,
        ]);

        // Reference dokumen polimorfik untuk recordMutation() — Branch
        // dipakai sebagai placeholder murni (pola sama test StockLedgerService
        // yang sudah ada, mis. StockLedgerConcurrencyTest), bukan dokumen
        // bisnis sungguhan karena tidak relevan untuk uji throughput ini.
        $referenceDocument = Branch::create([
            'code' => 'LR'.Str::upper(Str::random(8)),
            'name' => 'Referensi Uji Beban T3.11',
            'is_hq' => false,
            'is_active' => true,
        ]);

        $category = ProductCategory::factory()->create(['name' => 'Uji Beban T3.11']);
        $unit = Unit::factory()->create(['name' => 'Unit Uji Beban T3.11']);

        $products = $this->seedProducts($skuCount, $chunkSize, (string) $category->id, (string) $unit->id);

        $receiveLatenciesMs = $this->runReceivePhase($ledger, $branch, $referenceDocument, $products);

        $consumeTarget = $mutationTarget - $skuCount;
        $consumeLatenciesMs = $this->runConsumePhase($ledger, $branch, $referenceDocument, $products, $consumeTarget);

        $this->reportPercentiles('RECEIVE', $receiveLatenciesMs);
        $this->reportPercentiles('CONSUME', $consumeLatenciesMs);

        $totalMutations = DB::table('stock_mutations')->where('branch_id', $branch->id)->count();
        $this->info(sprintf(
            'Selesai — %d Product, %d baris stock_mutations (target %d) di cabang %s.',
            $skuCount,
            $totalMutations,
            $mutationTarget,
            $branch->code,
        ));

        return self::SUCCESS;
    }

    /**
     * Mengembalikan model `Product` LENGKAP (bukan hanya ID) — dipakai
     * ulang langsung di kedua fase berikutnya TANPA fetch ulang, supaya
     * angka yang diukur murni throughput `StockLedgerService`, bukan
     * tercampur overhead SELECT ekstra milik perintah ini sendiri.
     *
     * @return array<int, Product>
     */
    private function seedProducts(int $skuCount, int $chunkSize, string $categoryId, string $unitId): array
    {
        $this->info("Menyeed {$skuCount} produk (chunk {$chunkSize})...");
        $bar = $this->output->createProgressBar($skuCount);
        $products = [];
        $seeded = 0;
        $start = microtime(true);

        while ($seeded < $skuCount) {
            $take = min($chunkSize, $skuCount - $seeded);

            $chunk = Product::factory()->count($take)->create([
                'product_category_id' => $categoryId,
                'base_unit_id' => $unitId,
            ])->all();

            array_push($products, ...$chunk);
            $seeded += $take;
            $bar->advance($take);
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('Seed produk selesai dalam %.2f detik.', microtime(true) - $start));

        return $products;
    }

    /**
     * @param  array<int, Product>  $products
     * @return array<int, float>
     */
    private function runReceivePhase(StockLedgerService $ledger, Branch $branch, Branch $referenceDocument, array $products): array
    {
        $count = count($products);
        $this->info("Fase RECEIVE: {$count} operasi (satu batch besar per produk)...");
        $bar = $this->output->createProgressBar($count);
        $latenciesMs = [];

        foreach ($products as $product) {
            $start = microtime(true);
            DB::transaction(function () use ($ledger, $branch, $product, $referenceDocument): void {
                $ledger->receive($branch, $product, '100000.0000', '10000.00', now(), $referenceDocument, StockMutationType::Receipt);
            });
            $latenciesMs[] = (microtime(true) - $start) * 1000;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $latenciesMs;
    }

    /**
     * @param  array<int, Product>  $products
     * @return array<int, float>
     */
    private function runConsumePhase(StockLedgerService $ledger, Branch $branch, Branch $referenceDocument, array $products, int $consumeTarget): array
    {
        $this->info("Fase CONSUME: {$consumeTarget} operasi (kecil, acak lintas produk)...");
        $bar = $this->output->createProgressBar($consumeTarget);
        $latenciesMs = [];
        $products = array_values($products);
        $productCount = count($products);

        for ($i = 0; $i < $consumeTarget; $i++) {
            $product = $products[random_int(0, $productCount - 1)];

            $start = microtime(true);
            DB::transaction(function () use ($ledger, $branch, $product, $referenceDocument): void {
                $ledger->consume($branch, $product, '0.0100', $referenceDocument, StockMutationType::Sale);
            });
            $latenciesMs[] = (microtime(true) - $start) * 1000;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $latenciesMs;
    }

    /**
     * @param  array<int, float>  $latenciesMs
     */
    private function reportPercentiles(string $label, array $latenciesMs): void
    {
        $count = count($latenciesMs);

        if ($count === 0) {
            $this->warn("{$label}: tidak ada operasi tercatat.");

            return;
        }

        sort($latenciesMs);

        $p50 = $latenciesMs[(int) floor($count * 0.50)];
        $p95 = $latenciesMs[min($count - 1, (int) floor($count * 0.95))];
        $p99 = $latenciesMs[min($count - 1, (int) floor($count * 0.99))];
        $avg = array_sum($latenciesMs) / $count;
        $total = array_sum($latenciesMs) / 1000;

        $this->table(
            ["{$label} — {$count} operasi", 'ms'],
            [
                ['avg', number_format($avg, 2)],
                ['p50', number_format($p50, 2)],
                ['p95', number_format($p95, 2)],
                ['p99', number_format($p99, 2)],
                ['total (detik)', number_format($total, 2)],
            ],
        );
    }
}
