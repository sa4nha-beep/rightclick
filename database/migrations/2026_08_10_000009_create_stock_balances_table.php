<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache turunan kuantitas stok — T3.2. LOCAL (CLAUDE.md §7): tidak
     * disinkronkan antar node, tiap node menghitung ulang dari
     * `stock_batches` miliknya sendiri.
     *
     * Hanya ditulis `StockLedgerService` (bersamaan dengan setiap mutasi,
     * dalam transaksi yang sama) atau `php artisan stock:rebuild-balances`.
     * Selalu bisa dibangun ulang dari `SUM(qty_remaining)` atas
     * `stock_batches` — bila baris ini dan batch pernah tidak sinkron,
     * rebuild adalah kebenaran, bukan baris ini.
     */
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->branchId();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty_on_hand', 18, 4)->default(0);
            $table->timestampsTz();

            $table->unique(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
