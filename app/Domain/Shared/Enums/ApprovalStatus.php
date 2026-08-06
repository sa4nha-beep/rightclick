<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Status keputusan pada `approvals` (DB Design §4.1 `approval_status`).
 *
 * `pending` → `approved` atau `pending` → `rejected`, satu arah — tidak ada
 * jalan kembali ke `pending` (AP-01/AP-04, `App\Application\Services\ApprovalService`).
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire.
 */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}
