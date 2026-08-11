<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\ApprovalService;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Menyetujui permintaan TH5a/TH5b/TH5c tertunda (`ChangeProductSellingPriceAction`)
 * dan MENERAPKAN harga yang diajukan ke `products.selling_price` — dua
 * langkah yang HARUS terjadi bersamaan dalam satu transaksi, sama pola
 * `ApproveStockAdjustmentAction`/`ApproveSaleDiscountAction`.
 *
 * Bila menyetujui tidak sekaligus menerapkan (dua Action terpisah, dua klik
 * admin), ada jendela waktu Approval berstatus `approved` tapi harga produk
 * masih nilai lama — pelanggan bisa terus membayar harga salah sampai klik
 * kedua dilakukan.
 */
final class ApproveProductPriceChangeAction
{
    public function __construct(
        private readonly ApprovalService $approvals,
    ) {}

    public function execute(Approval $approval): Product
    {
        return DB::transaction(function () use ($approval) {
            if ($approval->approvable_type !== (new Product)->getMorphClass()) {
                throw new ApprovalException('Approval ini bukan permintaan perubahan harga produk.');
            }

            $product = Product::query()->findOrFail($approval->approvable_id);
            $proposedPrice = $approval->payload['proposed_selling_price'] ?? null;

            if ($proposedPrice === null) {
                throw new ApprovalException('Approval ini tidak menyimpan harga yang diajukan.');
            }

            $this->approvals->approve($approval);

            $product->update(['selling_price' => $proposedPrice]);

            return $product->refresh();
        });
    }
}
