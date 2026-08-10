<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membangun ulang `stock_balances` dari `SUM(qty_remaining)` atas
 * `stock_batches` (CLAUDE.md §7, T3.2). `stock_balances` adalah cache
 * turunan LOCAL — perintah ini adalah jalan pulih bila cache pernah tidak
 * sinkron dengan ledger. `stock_batches` (bentukan `stock_mutations`)
 * selalu sumber kebenaran, bukan baris di sini.
 */
class RebuildStockBalancesCommand extends Command
{
    protected $signature = 'stock:rebuild-balances';

    protected $description = 'Membangun ulang stock_balances dari SUM(qty_remaining) stock_batches';

    public function handle(): int
    {
        DB::transaction(function (): void {
            DB::table('stock_balances')->delete();

            $totals = StockBatch::withoutGlobalScope(BranchScope::class)
                ->selectRaw('branch_id, product_id, SUM(qty_remaining) as total_qty')
                ->groupBy('branch_id', 'product_id')
                ->get();

            $now = now();

            foreach ($totals as $row) {
                DB::table('stock_balances')->insert([
                    'id' => (string) Str::uuid7(),
                    'branch_id' => $row->getAttribute('branch_id'),
                    'product_id' => $row->getAttribute('product_id'),
                    'qty_on_hand' => $row->getAttribute('total_qty'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->info(sprintf('%d baris stock_balances dibangun ulang.', $totals->count()));
        });

        return self::SUCCESS;
    }
}
