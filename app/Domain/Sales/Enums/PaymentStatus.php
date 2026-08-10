<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

/**
 * Status pelunasan penjualan (DP/piutang parsial, T4.2). Dikunci
 * `FinalizeSaleAction` saat finalisasi berdasarkan `amount_paid` vs
 * `total_amount` — tidak pernah diubah manual setelahnya (R4, dokumen
 * final tidak dapat diedit).
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Dibayar',
            self::Partial => 'DP (Sebagian)',
            self::Paid => 'Lunas',
        };
    }
}
