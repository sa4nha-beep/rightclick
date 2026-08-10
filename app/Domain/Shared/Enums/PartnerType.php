<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Jenis mitra bisnis (T2.2). Satu Partner dapat berperan sebagai pemasok,
 * pelanggan, atau keduanya (mis. toko yang juga menjual barang bekas
 * kembali ke HAEN KOMPUTER) — karenanya `Both`, bukan dua tabel terpisah.
 */
enum PartnerType: string
{
    case Supplier = 'supplier';
    case Customer = 'customer';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Pemasok',
            self::Customer => 'Pelanggan',
            self::Both => 'Pemasok & Pelanggan',
        };
    }
}
