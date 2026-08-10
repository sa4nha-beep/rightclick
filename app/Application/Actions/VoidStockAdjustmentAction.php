<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Application\Services\StockLedgerService;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

/**
 * Void penyesuaian stok final (T3.5, R4). Sama pola dengan
 * `VoidStockOpnameAction`.
 */
final class VoidStockAdjustmentAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
    ) {}

    public function execute(StockAdjustment $adjustment, string $reason): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $reason) {
            $this->stockLedger->reverseForReference($adjustment, $adjustment);
            $this->documentStates->void($adjustment, $reason);

            return $adjustment->fresh();
        });
    }
}
