<?php

declare(strict_types=1);

namespace App\Domain\Sync\Enums;

/**
 * Status LOKAL `outbox_events` (T5.7). Penyederhanaan tiga status —
 * BUKAN 4 status protokol sinkronisasi penuh (`accepted`/`duplicate`/
 * `deferred`/`rejected`, CLAUDE.md §8) yang baru relevan setelah worker
 * sinkronisasi (T5.8) ada untuk menerjemahkan respons API ke status ini.
 * `deferred` khususnya TIDAK punya padanan di sini — event yang
 * `deferred` di sisi HQ tetap `pending` secara lokal (dicoba ulang),
 * persis seperti diminta CLAUDE.md §8: "`deferred`... Biarkan `pending`,
 * coba ulang."
 *
 * Enum ini berada di lapisan Domain — tidak boleh mengimpor apa pun dari
 * Laravel, Filament, maupun Livewire (LayeringTest).
 *
 * Berkas pertama di `App\Domain\Sync` — namespace baru, dipisah dari
 * domain lain karena representasi konsep sinkronisasi murni, bukan aturan
 * bisnis Sales/Inventory/Procurement/Finance.
 */
enum OutboxEventStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Kirim',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
        };
    }
}
