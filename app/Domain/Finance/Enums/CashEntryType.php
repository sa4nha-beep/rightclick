<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

/**
 * Jenis mutasi kas (`cash_entries.entry_type`, T5.4). Sama peran dengan
 * `App\Domain\Inventory\Enums\StockMutationType` — kategorisasi TERPISAH
 * dari `reference_type`/`reference_id` (yang menunjuk dokumen SPESIFIK
 * penyebabnya), untuk kebutuhan filter/laporan per JENIS pergerakan.
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire (LayeringTest).
 *
 * Berkas pertama di `App\Domain\Finance` — direktori ini sudah ada sejak
 * struktur awal proyek (`.gitkeep`) tapi baru terisi kode nyata di T5.4.
 */
enum CashEntryType: string
{
    case SalePayment = 'sale_payment';
    case PurchasePayment = 'purchase_payment';
    case VoidReversal = 'void_reversal';

    public function label(): string
    {
        return match ($this) {
            self::SalePayment => 'Pembayaran Penjualan',
            self::PurchasePayment => 'Pembayaran Hutang',
            self::VoidReversal => 'Pembalikan Void',
        };
    }
}
