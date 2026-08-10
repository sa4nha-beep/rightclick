<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Serial number pada baris transaksi — T3.7, R3: "Serial dicatat
     * sebagai field pada baris transaksi sejak MVP." Sengaja hanya field
     * jsonb pada baris, BUKAN tabel registry terpisah dengan status
     * (in_stock/sold/transferred) — "Unit registry penuh pasca-MVP" adalah
     * batas cakupan eksplisit CLAUDE.md §3.
     *
     * Ditaruh pada `*_lines` (bukan `stock_mutations`) — R3 secara literal
     * menyebut "baris transaksi", dan ini menghindari kerumitan memecah
     * satu daftar serial ke beberapa baris `stock_mutations` saat FIFO
     * mengonsumsi dari lebih dari satu batch sekaligus (§16 — MVP sengaja
     * tidak melacak serial per batch).
     */
    public function up(): void
    {
        Schema::table('stock_opname_lines', function (Blueprint $table) {
            $table->jsonb('serial_numbers')->nullable()->after('unit_cost');
        });

        Schema::table('stock_adjustment_lines', function (Blueprint $table) {
            $table->jsonb('serial_numbers')->nullable()->after('unit_cost');
        });

        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->jsonb('serial_numbers')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_lines', function (Blueprint $table) {
            $table->dropColumn('serial_numbers');
        });

        Schema::table('stock_adjustment_lines', function (Blueprint $table) {
            $table->dropColumn('serial_numbers');
        });

        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->dropColumn('serial_numbers');
        });
    }
};
