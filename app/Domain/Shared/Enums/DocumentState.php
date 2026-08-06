<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Status siklus hidup dokumen transaksi (R4, DB Design §3 `document_state`).
 *
 * `draft` → `final` → `void`, satu arah. Dokumen `final` tidak dapat diedit;
 * koreksi hanya melalui void beralasan + dokumen baru. `void` bersifat
 * terminal — tidak ada jalan kembali (DB Design §8.3: void tidak menghapus
 * mutasi lama, hanya menerbitkan mutasi berlawanan).
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire.
 */
enum DocumentState: string
{
    case Draft = 'draft';
    case Final = 'final';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Final => 'Final',
            self::Void => 'Batal',
        };
    }
}
