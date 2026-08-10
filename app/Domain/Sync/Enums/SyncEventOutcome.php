<?php

declare(strict_types=1);

namespace App\Domain\Sync\Enums;

/**
 * 4 status hasil per event sinkronisasi (CLAUDE.md §8) — BEDA dari
 * `OutboxEventStatus` (status LOKAL 3-nilai di sisi cabang). Enum ini
 * adalah kosakata PROTOKOL (respons `POST /api/v1/sync/events`), yang
 * kemudian DITERJEMAHKAN oleh `OutboxDispatcher` (cabang) ke
 * `OutboxEventStatus`:
 *
 *   Accepted/Duplicate → OutboxEventStatus::Sent ("bukan kegagalan")
 *   Deferred           → OutboxEventStatus::Pending (coba ulang)
 *   Rejected           → OutboxEventStatus::Failed (tampilkan di panel admin)
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire.
 */
enum SyncEventOutcome: string
{
    case Accepted = 'accepted';
    case Duplicate = 'duplicate';
    case Deferred = 'deferred';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Diterima',
            self::Duplicate => 'Duplikat',
            self::Deferred => 'Ditunda',
            self::Rejected => 'Ditolak',
        };
    }
}
