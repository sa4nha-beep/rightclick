<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * Satu baris hasil konsumsi FIFO — batch yang terpakai, kuantitas yang
 * diambil dari batch itu, dan `unit_cost` batch tersebut (T3.2).
 *
 * `StockLedgerService::consume()` mengembalikan koleksi DTO ini agar
 * pemanggil (mis. T3.6 transfer) tahu persis batch mana saja yang terpakai
 * dan biayanya — dibutuhkan untuk membangun kembali batch tujuan dengan
 * biaya yang benar tanpa harus membaca ulang `stock_mutations`.
 */
final readonly class StockConsumption
{
    public function __construct(
        public string $stockBatchId,
        public string $quantity,
        public string $unitCost,
    ) {}
}
