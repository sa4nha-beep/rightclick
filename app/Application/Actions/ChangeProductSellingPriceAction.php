<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\ApprovalService;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Setting;
use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Penutupan PT16 — TH5a/TH5b/TH5c (CLAUDE.md §10). Dipanggil dari
 * `EditProduct::handleRecordUpdate()` HANYA saat `selling_price` benar-benar
 * berubah — perubahan field lain tetap lewat `$record->update()` biasa.
 *
 * Owner DIKECUALIKAN dari ketiga ambang (pola sama TH1/TH2/TH3/TH4) —
 * satu-satunya peran yang bisa menaikkan/menurunkan harga berapa pun tanpa
 * menunggu keputusan siapa pun, karena Owner ADALAH puncak rantai
 * persetujuan itu sendiri.
 *
 * Di bawah ambang → diterapkan LANGSUNG ke `products.selling_price`. Di
 * atas ambang → `selling_price` TETAP TIDAK BERUBAH, nilai yang diajukan
 * disimpan di `approvals.payload` (lihat migration
 * `2026_08_11_000011_add_payload_to_approvals_table.php` untuk alasan
 * lengkap mengapa Product butuh mekanisme ini, beda dari Sale/StockAdjustment/
 * PurchaseOrder yang nilai barunya sudah ada di baris draft mereka sendiri).
 *
 * TH5c dicek DULU dan SELALU (tidak peduli besaran persentase) — baru
 * TH5a/TH5b dicek sebagai perbandingan persentase terhadap harga lama.
 * HPP batch tertua dicek LINTAS CABANG (tanpa filter branch_id) — `Product`
 * sendiri bukan tabel branch-scoped (REPLICATED), dan harga jual adalah
 * satu nilai global untuk seluruh perusahaan, jadi risiko "dijual di bawah
 * modal" berlaku di cabang mana pun batch termurah itu berada.
 */
final class ChangeProductSellingPriceAction
{
    public function __construct(
        private readonly ApprovalService $approvals,
    ) {}

    public function execute(Product $product, string $newSellingPrice): Product|Approval
    {
        $actor = Auth::user();

        if ($actor === null || ! $actor->can('manage_product_prices')) {
            throw new AuthorizationException('Tidak berwenang mengubah harga jual produk.');
        }

        $oldSellingPrice = (string) $product->selling_price;

        if (bccomp($oldSellingPrice, $newSellingPrice, 2) === 0) {
            return $product;
        }

        if ($actor->hasRole('owner') || ! $this->exceedsThreshold($product, $oldSellingPrice, $newSellingPrice)) {
            $product->update(['selling_price' => $newSellingPrice]);

            return $product->refresh();
        }

        return $this->approvals->request(
            $product,
            (string) $actor->id,
            $actor->default_branch_id,
            payload: [
                'proposed_selling_price' => $newSellingPrice,
                'previous_selling_price' => $oldSellingPrice,
            ],
        );
    }

    private function exceedsThreshold(Product $product, string $oldSellingPrice, string $newSellingPrice): bool
    {
        $blockBelowCost = (bool) (Setting::get('price.block_below_cost') ?? true);

        if ($blockBelowCost) {
            $oldestBatchCost = $this->oldestBatchUnitCost($product);

            if ($oldestBatchCost !== null && bccomp($newSellingPrice, $oldestBatchCost, 2) < 0) {
                return true;
            }
        }

        if (bccomp($oldSellingPrice, '0', 2) === 0) {
            return false;
        }

        $diff = bcsub($newSellingPrice, $oldSellingPrice, 6);

        if (bccomp($diff, '0', 6) > 0) {
            $maxIncreaseFraction = (string) (Setting::get('price.threshold_increase') ?? '0.10');
            $allowedIncrease = bcmul($oldSellingPrice, $maxIncreaseFraction, 6);

            return bccomp($diff, $allowedIncrease, 6) > 0;
        }

        if (bccomp($diff, '0', 6) < 0) {
            $maxDecreaseFraction = (string) (Setting::get('price.threshold_decrease') ?? '0.05');
            $allowedDecrease = bcmul($oldSellingPrice, $maxDecreaseFraction, 6);

            return bccomp(bcmul($diff, '-1', 6), $allowedDecrease, 6) > 0;
        }

        return false;
    }

    private function oldestBatchUnitCost(Product $product): ?string
    {
        $batch = StockBatch::withoutGlobalScope(BranchScope::class)
            ->where('product_id', $product->id)
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        return $batch === null ? null : (string) $batch->unit_cost;
    }
}
