<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Jenis dokumen transaksi yang memperoleh nomor terpusat dari
 * `App\Application\Services\DocumentNumberService` (T1.7).
 *
 * Nilai enum (`value`) adalah string yang disimpan pada kolom
 * `document_sequences.document_type` (DB Design §4.1). `prefix()` adalah kode
 * tiga huruf yang muncul pada nomor dokumen tercetak, mis. `HKA/SAL/2608/00142`
 * (HS-UI-RIGHTCLICK-v1.1 §8.4, HS-API-RIGHTCLICK-v1.0).
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire (LayeringTest).
 *
 * Awalnya hanya memuat `sale` → `SAL`, satu-satunya contoh nomor yang
 * terdokumentasi. T3.4-T3.6 menambah `Opname`/`Adjustment`/
 * `TransferDispatch`/`TransferReceipt` dengan kode yang DITURUNKAN SENDIRI (self-derived)
 * (OPN/ADJ/TRO/TRI) — sama seperti penomoran task T2.x/T3.x sendiri harus
 * diselederivasi karena `HS-TASKS-RIGHTCLICK-v1.1`/`HS-DB-RIGHTCLICK-v1.0`
 * tidak ada di repositori (lihat CLAUDE.md §11 catatan Fase 2/3). Ditandai
 * eksplisit untuk direkonsiliasi terhadap dokumen asli bila tersedia —
 * BUKAN diam-diam dianggap final. T4.1 menambah `CashierShift` → `SFT`
 * (self-derived, sama alasan). T4.3 menambah `SaleReturn` → `RET`
 * (self-derived). T5.1 menambah `PurchaseOrder` → `PO` (self-derived, dua
 * huruf — beda pola dari yang lain karena "purchase order" secara umum
 * disingkat begitu, bukan tiga huruf diambil dari nama Indonesia seperti
 * ADJ/OPN; direkonsiliasi bila `HS-DB-RIGHTCLICK-v1.0` tersedia). T5.2
 * menambah `GoodsReceipt` → `PB` (self-derived, "Penerimaan Barang" — pola
 * Indonesia dua huruf, KEMBALI ke gaya ADJ/OPN, bukan gaya `PO`; disengaja
 * inkonsisten antara T5.1 dan T5.2 karena "PO" adalah istilah yang dipakai
 * apa adanya dalam bahasa Indonesia sehari-hari toko, sedangkan "goods
 * receipt" tidak — direkonsiliasi bersama seluruh prefix self-derived
 * lainnya) dan `PurchaseInvoice` → `INV` (self-derived, tiga huruf dari
 * "invoice" — sengaja BUKAN "FP" untuk menghindari kerancuan dengan
 * "Faktur Pajak", istilah pajak yang tidak relevan bagi HAEN KOMPUTER yang
 * non-PKP, R2).
 */
enum DocumentType: string
{
    case Sale = 'sale';
    case Opname = 'opname';
    case Adjustment = 'adjustment';
    case TransferDispatch = 'transfer_dispatch';
    case TransferReceipt = 'transfer_receipt';
    case CashierShift = 'cashier_shift';
    case SaleReturn = 'sale_return';
    case PurchaseOrder = 'purchase_order';
    case GoodsReceipt = 'goods_receipt';
    case PurchaseInvoice = 'purchase_invoice';

    /**
     * Kode tiga huruf pada nomor dokumen tercetak.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Sale => 'SAL',
            self::Opname => 'OPN',
            self::Adjustment => 'ADJ',
            self::TransferDispatch => 'TRO',
            self::TransferReceipt => 'TRI',
            self::CashierShift => 'SFT',
            self::SaleReturn => 'RET',
            self::PurchaseOrder => 'PO',
            self::GoodsReceipt => 'PB',
            self::PurchaseInvoice => 'INV',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Penjualan',
            self::Opname => 'Stock Opname',
            self::Adjustment => 'Penyesuaian Stok',
            self::TransferDispatch => 'Transfer Keluar',
            self::TransferReceipt => 'Transfer Masuk',
            self::CashierShift => 'Shift Kasir',
            self::SaleReturn => 'Retur Penjualan',
            self::PurchaseOrder => 'Purchase Order',
            self::GoodsReceipt => 'Penerimaan Barang',
            self::PurchaseInvoice => 'Faktur Pembelian',
        };
    }
}
