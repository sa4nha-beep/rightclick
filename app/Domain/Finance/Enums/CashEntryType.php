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
 *
 * `ReceivableCollection` (T5.5) SENGAJA dipisah dari `SalePayment` —
 * keduanya sama-sama "uang masuk dari penjualan", tapi `SalePayment`
 * adalah kas yang diterima PADA SAAT transaksi (DP/lunas di muka,
 * `FinalizeSaleAction`), sedangkan `ReceivableCollection` adalah
 * pelunasan piutang yang dikumpulkan BELAKANGAN, lewat
 * `RecordReceivablePaymentAction` — dua peristiwa yang layak dibedakan
 * untuk kebutuhan laporan (kapan uang benar-benar diterima).
 */
enum CashEntryType: string
{
    case SalePayment = 'sale_payment';
    case PurchasePayment = 'purchase_payment';
    case ReceivableCollection = 'receivable_collection';
    case VoidReversal = 'void_reversal';

    public function label(): string
    {
        return match ($this) {
            self::SalePayment => 'Pembayaran Penjualan',
            self::PurchasePayment => 'Pembayaran Hutang',
            self::ReceivableCollection => 'Pelunasan Piutang',
            self::VoidReversal => 'Pembalikan Void',
        };
    }
}
