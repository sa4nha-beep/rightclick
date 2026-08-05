<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Peran node dalam topologi tiga node RIGHTCLICK.
 *
 * HQ adalah satu-satunya penulis master data; node cabang menerima master data
 * sebagai replika read-only dan mengirim transaksi final melalui outbox.
 * Referensi: HS-ARCH-RIGHTCLICK-v1.1 bagian 1.1 dan 1.3.
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire.
 */
enum NodeRole: string
{
    case Hq = 'hq';
    case Branch = 'branch';

    public function isHq(): bool
    {
        return $this === self::Hq;
    }

    public function isBranch(): bool
    {
        return $this === self::Branch;
    }

    /**
     * Node yang boleh menulis master data (M02). Hanya HQ.
     */
    public function canWriteMasterData(): bool
    {
        return $this->isHq();
    }

    public function label(): string
    {
        return match ($this) {
            self::Hq => 'Node HQ',
            self::Branch => 'Node Cabang',
        };
    }
}
