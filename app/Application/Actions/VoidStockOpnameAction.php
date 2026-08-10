<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Application\Services\StockLedgerService;
use App\Infrastructure\Persistence\Models\StockOpname;
use Illuminate\Support\Facades\DB;

/**
 * Void stock opname final (T3.4, R4). Membalik mutasi lebih dulu, baru
 * mengunci status dokumen — bila pembalikan gagal (mis. batch yang
 * diterbitkan opname ini sudah terkonsumsi dokumen lain), dokumen tetap
 * `final` (transaksi rollback), bukan `void` dengan efek yang gagal
 * diterapkan.
 */
final class VoidStockOpnameAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
    ) {}

    public function execute(StockOpname $opname, string $reason): StockOpname
    {
        return DB::transaction(function () use ($opname, $reason) {
            $this->stockLedger->reverseForReference($opname, $opname);
            $this->documentStates->void($opname, $reason);

            return $opname->fresh();
        });
    }
}
