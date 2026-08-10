<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

/**
 * Jenis stock opname (`stock_opnames.type`, T3.4).
 *
 * `OpeningBalance` (R9 — "Stok awal via opname fisik") dipisahkan dari
 * `Periodic` karena lebih sensitif: mensyaratkan permission
 * `adjust_opening_balance` (Admin/Owner saja, bukan Gudang — lihat
 * `PermissionSeeder`) dan `unit_cost` WAJIB diisi eksplisit per baris
 * (tidak ada batch sebelumnya untuk diambil biayanya).
 */
enum StockOpnameType: string
{
    case Periodic = 'periodic';
    case OpeningBalance = 'opening_balance';

    public function label(): string
    {
        return match ($this) {
            self::Periodic => 'Opname Berkala',
            self::OpeningBalance => 'Saldo Awal',
        };
    }
}
