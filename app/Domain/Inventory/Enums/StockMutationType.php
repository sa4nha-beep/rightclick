<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

use LogicException;

/**
 * Jenis mutasi stok (`stock_mutations.mutation_type`, T3.2, R1).
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire (LayeringTest).
 *
 * `Sale`/`SaleReturn` belum dipakai kode mana pun di Fase 3 — sengaja
 * disertakan lebih awal karena bentuknya (arah +/-, referensi dokumen)
 * sudah pasti dari CLAUDE.md §11 Fase 4, menghindari perlu mengubah enum
 * ini lagi saat Sales dibangun.
 */
enum StockMutationType: string
{
    case Receipt = 'receipt';
    case AdjustmentIncrease = 'adjustment_increase';
    case AdjustmentDecrease = 'adjustment_decrease';
    case OpnameCorrectionIncrease = 'opname_correction_increase';
    case OpnameCorrectionDecrease = 'opname_correction_decrease';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case VoidReversal = 'void_reversal';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Penerimaan',
            self::AdjustmentIncrease => 'Penyesuaian Naik',
            self::AdjustmentDecrease => 'Penyesuaian Turun',
            self::OpnameCorrectionIncrease => 'Koreksi Opname Naik',
            self::OpnameCorrectionDecrease => 'Koreksi Opname Turun',
            self::TransferOut => 'Transfer Keluar',
            self::TransferIn => 'Transfer Masuk',
            self::Sale => 'Penjualan',
            self::SaleReturn => 'Retur Penjualan',
            self::VoidReversal => 'Pembalikan Void',
        };
    }

    /**
     * Arah mutasi — true bila menambah stok (batch baru/qty_remaining naik),
     * false bila mengurangi (konsumsi FIFO). `VoidReversal` tidak masuk
     * daftar tetap karena arahnya mengikuti mutasi asal yang dibalik —
     * `StockLedgerService::reverseForReference()` menentukannya sendiri per
     * baris, bukan dari enum ini.
     */
    public function isIncrease(): bool
    {
        return match ($this) {
            self::Receipt, self::AdjustmentIncrease, self::OpnameCorrectionIncrease,
            self::TransferIn, self::SaleReturn => true,
            self::AdjustmentDecrease, self::OpnameCorrectionDecrease,
            self::TransferOut, self::Sale => false,
            self::VoidReversal => throw new LogicException(
                'VoidReversal tidak punya arah tetap — ditentukan per baris oleh StockLedgerService::reverseForReference().'
            ),
        };
    }
}
