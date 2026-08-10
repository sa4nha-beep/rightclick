<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

/**
 * Metode pembayaran penjualan (multi-payment, T4.1). Self-derived — tidak
 * ada daftar metode pembayaran terdokumentasi di CLAUDE.md/HS-*, diturunkan
 * dari kebutuhan retail umum. Ditandai untuk direkonsiliasi bila
 * `HS-DB-RIGHTCLICK-v1.0` tersedia di sesi berikutnya (lihat catatan
 * `DocumentType`).
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Card => 'Kartu',
            self::Transfer => 'Transfer',
            self::Qris => 'QRIS',
            self::Other => 'Lainnya',
        };
    }

    /**
     * Dipakai `CloseCashierShiftAction` untuk menghitung kas fisik yang
     * seharusnya ada di laci — hanya `Cash` yang memengaruhi saldo kas.
     */
    public function isCash(): bool
    {
        return $this === self::Cash;
    }
}
