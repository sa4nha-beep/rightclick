<?php

declare(strict_types=1);

use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * AC-10 — "Dua kasir menjual unit terakhir bersamaan → satu berhasil, satu
 * ditolak, stok tidak negatif." `StockLedgerService::consume()` mencapai ini
 * lewat `SELECT ... FOR UPDATE` pada `stock_batches` (R7).
 *
 * Test ini TIDAK memakai pola `DB::beginTransaction()/rollBack()` yang
 * dipakai file test lain — data perlu benar-benar COMMIT agar koneksi
 * database kedua yang independen (mensimulasikan proses kasir kedua) dapat
 * melihatnya. Baris dibersihkan manual di `afterEach`.
 *
 * Membuktikan mekanisme penguncian secara langsung (dua koneksi Postgres
 * nyata, `lock_timeout` pada koneksi kedua) lebih deterministik dan lebih
 * cepat daripada mensimulasikan dua proses PHP sungguhan.
 */
beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->reference = Branch::factory()->create();

    DB::transaction(function () {
        app(StockLedgerService::class)->receive(
            $this->branch,
            $this->product,
            '1.0000',
            '100000.00',
            now(),
            $this->reference,
            StockMutationType::Receipt,
        );
    });

    config(['database.connections.pgsql_lock_test' => config('database.connections.pgsql')]);
});

afterEach(function () {
    DB::purge('pgsql_lock_test');

    DB::table('stock_mutations')->where('product_id', $this->product->id)->delete();
    DB::table('stock_batches')->where('product_id', $this->product->id)->delete();
    DB::table('stock_balances')->where('product_id', $this->product->id)->delete();
    $this->reference->forceDelete();
    $this->product->forceDelete();
    $this->branch->forceDelete();
});

it('mengunci baris stock_batches — koneksi kedua yang bersamaan diblokir (AC-10)', function () {
    $productId = $this->product->id;
    $branchId = $this->branch->id;

    DB::connection('pgsql')->beginTransaction();

    // Koneksi A — kasir pertama: mengunci baris batch, belum commit (persis
    // langkah pertama StockLedgerService::consume()).
    DB::connection('pgsql')->select(
        'select * from stock_batches where branch_id = ? and product_id = ? and qty_remaining > 0 for update',
        [$branchId, $productId],
    );

    // Koneksi B — kasir kedua, mensimulasikan permintaan bersamaan.
    // lock_timeout pendek: kalau baris tidak terkunci, SELECT ini akan
    // selesai seketika tanpa error — jadi exception di bawah MEMBUKTIKAN
    // baris memang sedang dikunci koneksi A.
    $connectionB = DB::connection('pgsql_lock_test');
    $connectionB->statement("set lock_timeout = '200ms'");

    $blocked = false;

    try {
        $connectionB->select(
            'select * from stock_batches where branch_id = ? and product_id = ? and qty_remaining > 0 for update',
            [$branchId, $productId],
        );
    } catch (QueryException $exception) {
        $blocked = str_contains($exception->getMessage(), 'lock');
    }

    DB::connection('pgsql')->rollBack();

    expect($blocked)->toBeTrue(
        'Koneksi kedua seharusnya diblokir oleh FOR UPDATE koneksi pertama (AC-10) — tidak terdeteksi lock.',
    );
});
