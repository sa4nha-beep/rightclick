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
 * (self-derived). Kode prefix untuk `po`/`gr` (T5.x) masih belum ditetapkan.
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
        };
    }
}
