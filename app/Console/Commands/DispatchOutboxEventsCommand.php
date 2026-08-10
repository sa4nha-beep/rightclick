<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\Sync\OutboxDispatcher;
use Illuminate\Console\Command;

/**
 * Menjalankan `OutboxDispatcher` (T5.8) — dijadwalkan via cron di node
 * CABANG (CLAUDE.md §14, worker DIBATASI 1 PROSES, i3-7100 2 core).
 * Tidak melakukan apa pun di node HQ (`hq_url`/`token` kosong di `.env`
 * HQ, `OutboxDispatcher::dispatch()` langsung kembali tanpa memanggil
 * apa pun — lihat docblock kelas).
 */
class DispatchOutboxEventsCommand extends Command
{
    protected $signature = 'sync:dispatch';

    protected $description = 'Mengirim outbox_events pending ke HQ (T5.8)';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $result = $dispatcher->dispatch();

        $this->info(sprintf(
            'Sinkronisasi selesai — terkirim: %d, ditunda: %d, ditolak: %d.',
            $result['sent'],
            $result['deferred'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
