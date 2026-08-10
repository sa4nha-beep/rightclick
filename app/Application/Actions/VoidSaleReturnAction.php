<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Application\Services\StockLedgerService;
use App\Infrastructure\Persistence\Models\SaleReturn;
use Illuminate\Support\Facades\DB;

/**
 * Void retur penjualan final (T4.3, R4). Sama pola dengan
 * `VoidStockAdjustmentAction`/`VoidSaleAction` — barang yang tadi masuk
 * kembali ke stok via retur ditarik lagi lewat mutasi berlawanan (bukan
 * menghapus mutasi lama, DB Design §8.3).
 *
 * `OutboxService` (T5.7 retrofit, simpul kritis): `sale_return.voided`.
 */
final class VoidSaleReturnAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(SaleReturn $saleReturn, string $reason): SaleReturn
    {
        return DB::transaction(function () use ($saleReturn, $reason) {
            $this->stockLedger->reverseForReference($saleReturn, $saleReturn);
            $this->documentStates->void($saleReturn, $reason);
            $this->outbox->record($saleReturn->branch, $saleReturn, 'sale_return.voided');

            return $saleReturn->fresh();
        });
    }
}
