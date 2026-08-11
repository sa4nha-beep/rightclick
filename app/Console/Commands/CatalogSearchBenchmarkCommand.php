<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Infrastructure\Persistence\Models\Unit;
use Illuminate\Console\Command;

/**
 * Penutup gap T2.9/UT13/NFR-04 terhadap `HS-TASKS-RIGHTCLICK-v1.1`
 * ("pencarian produk < 1 detik pada 20.000 SKU"). T3.11 (`stock:load-test`)
 * EKSPLISIT tidak mencakup ini — cakupannya disepakati sempit hanya
 * throughput `StockLedgerService`, "query katalog POS TIDAK dicakup"
 * (lihat docblock `StockLoadTestCommand`). Perintah ini mengisi kesenjangan
 * itu, pola operasional yang sama (seed → ukur → bersihkan, dapat
 * dijalankan ulang di hardware sungguhan kapan pun).
 *
 * Query yang diukur adalah SALINAN PERSIS `PosTerminal::products()`
 * (T4.4) — bukan reimplementasi bebas — supaya angka yang dilaporkan
 * benar-benar mewakili apa yang dieksekusi POS sungguhan. Bila query di
 * `PosTerminal::products()` berubah, perintah ini perlu diperbarui
 * mengikutinya.
 *
 * PERINGATAN LINGKUNGAN (sama seperti `stock:load-test`): dijalankan di
 * container Docker dev, BUKAN hardware i3-7100 (CLAUDE.md §14) target
 * produksi. Angka absolut TIDAK BOLEH dipakai langsung untuk keputusan
 * kapasitas — wajib diverifikasi ulang di hardware sungguhan sebelum
 * go-live.
 */
class CatalogSearchBenchmarkCommand extends Command
{
    protected $signature = 'catalog:search-benchmark
        {--skus=20000 : Jumlah Product yang diseed}
        {--searches=200 : Jumlah pencarian yang dijalankan dan diukur}
        {--chunk=1000 : Ukuran chunk saat bulk-insert Product}';

    protected $description = 'T2.9/UT13/NFR-04 — uji kinerja pencarian katalog PosTerminal::products() pada volume SKU besar';

    public function handle(): int
    {
        $skuCount = (int) $this->option('skus');
        $searchCount = (int) $this->option('searches');
        $chunkSize = (int) $this->option('chunk');

        $this->warn('Dijalankan di container Docker dev — angka BUKAN representasi hardware i3-7100 produksi. Lihat docblock berkas ini.');

        $category = ProductCategory::factory()->create(['name' => 'Uji Beban Katalog T2.9']);
        $unit = Unit::factory()->create(['name' => 'Unit Uji Beban Katalog T2.9']);

        $products = $this->seedProducts($skuCount, $chunkSize, (string) $category->id, (string) $unit->id);

        $latenciesMs = $this->runSearchPhase($products, $searchCount);

        $this->reportPercentiles($latenciesMs);

        $this->info('Membersihkan data uji...');
        Product::query()->where('product_category_id', $category->id)->forceDelete();
        $category->forceDelete();
        $unit->forceDelete();

        $p95 = $this->percentile($latenciesMs, 0.95);
        if ($p95 > 1000.0) {
            $this->error(sprintf('p95 (%.2f ms) melebihi target NFR-04 (< 1 detik).', $p95));

            return self::FAILURE;
        }

        $this->info(sprintf('Selesai — p95 %.2f ms, di bawah target NFR-04 (< 1 detik).', $p95));

        return self::SUCCESS;
    }

    /**
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
    private function runSearchPhase(array $products, int $searchCount): array
    {
        $this->info("Menjalankan {$searchCount} pencarian (substring acak dari nama/SKU produk sungguhan)...");
        $bar = $this->output->createProgressBar($searchCount);
        $latenciesMs = [];
        $products = array_values($products);
        $productCount = count($products);

        for ($i = 0; $i < $searchCount; $i++) {
            $product = $products[random_int(0, $productCount - 1)];
            $term = $this->randomSubstring(random_int(0, 1) === 0 ? $product->name : $product->sku);

            $start = microtime(true);
            // SALINAN PERSIS query PosTerminal::products() (T4.4) — lihat docblock kelas.
            Product::query()
                ->where('is_active', true)
                ->when($term !== '', fn ($query) => $query->where(
                    fn ($sub) => $sub->where('name', 'ilike', "%{$term}%")
                        ->orWhere('sku', 'ilike', "%{$term}%"),
                ))
                ->orderBy('name')
                ->limit(40)
                ->get(['id', 'sku', 'name', 'selling_price']);
            $latenciesMs[] = (microtime(true) - $start) * 1000;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $latenciesMs;
    }

    private function randomSubstring(string $value): string
    {
        $length = mb_strlen($value);

        if ($length < 3) {
            return $value;
        }

        $substringLength = random_int(3, min(8, $length));
        $start = random_int(0, $length - $substringLength);

        return mb_substr($value, $start, $substringLength);
    }

    /**
     * @param  array<int, float>  $latenciesMs
     */
    private function reportPercentiles(array $latenciesMs): void
    {
        $count = count($latenciesMs);

        if ($count === 0) {
            $this->warn('Tidak ada pencarian tercatat.');

            return;
        }

        $this->table(
            ["Pencarian katalog — {$count} operasi", 'ms'],
            [
                ['avg', number_format(array_sum($latenciesMs) / $count, 2)],
                ['p50', number_format($this->percentile($latenciesMs, 0.50), 2)],
                ['p95', number_format($this->percentile($latenciesMs, 0.95), 2)],
                ['p99', number_format($this->percentile($latenciesMs, 0.99), 2)],
            ],
        );
    }

    /**
     * @param  array<int, float>  $latenciesMs
     */
    private function percentile(array $latenciesMs, float $fraction): float
    {
        $count = count($latenciesMs);

        if ($count === 0) {
            return 0.0;
        }

        $sorted = $latenciesMs;
        sort($sorted);

        return $sorted[min($count - 1, (int) floor($count * $fraction))];
    }
}
