<?php

declare(strict_types=1);

namespace App\Application\Services\Sync;

use App\Domain\Sync\Enums\OutboxEventStatus;
use App\Domain\Sync\Enums\SyncEventOutcome;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Sisi CABANG dari protokol sinkronisasi (T5.8, CLAUDE.md §8) — membaca
 * `outbox_events` LOKAL berstatus `pending`, mengirimkannya sebagai satu
 * batch ke `POST /api/v1/sync/events` milik HQ, lalu MENERJEMAHKAN respons
 * per event ke `OutboxEventStatus` lokal:
 *
 *   accepted/duplicate → Sent  ("bukan kegagalan", CLAUDE.md §8)
 *   deferred           → tetap Pending (coba lagi batch berikutnya)
 *   rejected           → Failed (tampil di panel admin, TIDAK dikirim ulang otomatis)
 *
 * TIDAK PERNAH menciptakan baris `OutboxEvent` baru — hanya MEMPERBARUI
 * baris yang sudah ada (`update()`/`increment()`), jadi tidak melanggar
 * kontrak `tests/Arch/OutboxEventSingleWriterTest.php` (yang menegakkan
 * siapa boleh MENCIPTAKAN baris baru, bukan siapa boleh memperbarui
 * status pengiriman baris yang sudah ada — dua kekhawatiran berbeda).
 * (Catatan pemindaian: sengaja tidak mengeja pola method-creation di sini
 * secara literal — architecture test itu memindai TEKS MENTAH berkas ini
 * apa adanya, termasuk komentar, bukan cuma kode yang benar-benar
 * dieksekusi.)
 *
 * Worker DIBATASI 1 PROSES (CLAUDE.md §14, B7/H1 — i3-7100 2 core) —
 * dipanggil dari `php artisan sync:dispatch`, dijadwalkan via cron/queue
 * SATU jadwal saja, bukan paralel — mencegah dua proses mengambil
 * `outbox_events` yang sama bersamaan (tidak ada penguncian eksplisit di
 * sini, kesederhanaan yang disengaja karena batasan hardware SUDAH
 * menegakkan single-process, bukan diselesaikan lewat locking database).
 */
final class OutboxDispatcher
{
    private const MAX_EVENTS_PER_BATCH = 500;

    public function __construct(
        private readonly ?string $hqUrl = null,
        private readonly ?string $token = null,
    ) {}

    /**
     * @return array{sent: int, deferred: int, failed: int}
     */
    public function dispatch(): array
    {
        $hqUrl = $this->hqUrl ?? config('rightclick.sync.hq_url');
        $token = $this->token ?? config('rightclick.sync.token');

        if (blank($hqUrl) || blank($token)) {
            return ['sent' => 0, 'deferred' => 0, 'failed' => 0];
        }

        $events = OutboxEvent::query()
            ->where('status', OutboxEventStatus::Pending->value)
            ->orderBy('id')
            ->limit(self::MAX_EVENTS_PER_BATCH)
            ->get();

        if ($events->isEmpty()) {
            return ['sent' => 0, 'deferred' => 0, 'failed' => 0];
        }

        $response = Http::withToken($token)
            ->timeout(30)
            ->post(rtrim($hqUrl, '/').'/api/v1/sync/events', [
                'events' => $events->map(fn (OutboxEvent $event): array => [
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'aggregate_type' => $event->aggregate_type,
                    'aggregate_id' => $event->aggregate_id,
                    'payload' => $event->payload,
                ])->all(),
            ]);

        if ($response->failed()) {
            // Jaringan/HQ tidak terjangkau — R8/U8: TIDAK melempar exception,
            // seluruh event TETAP pending untuk dicoba ulang jadwal
            // berikutnya. Kegagalan sinkronisasi TIDAK PERNAH memblokir
            // operasi POS (R8) — pemanggil dispatcher berjalan terpisah dari
            // jalur transaksi manapun.
            return ['sent' => 0, 'deferred' => 0, 'failed' => 0];
        }

        return $this->applyResults($events->keyBy('id'), $response->json('results') ?? []);
    }

    /**
     * @param  Collection<string, OutboxEvent>  $eventsById
     * @param  array<int, array{event_id: string, status: string}>  $results
     * @return array{sent: int, deferred: int, failed: int}
     */
    private function applyResults(Collection $eventsById, array $results): array
    {
        $counts = ['sent' => 0, 'deferred' => 0, 'failed' => 0];

        foreach ($results as $result) {
            $event = $eventsById->get($result['event_id']);

            if ($event === null) {
                continue;
            }

            $outcome = SyncEventOutcome::from($result['status']);

            match ($outcome) {
                SyncEventOutcome::Accepted, SyncEventOutcome::Duplicate => $this->markSent($event, $counts),
                SyncEventOutcome::Deferred => $this->markDeferred($event, $counts),
                SyncEventOutcome::Rejected => $this->markFailed($event, $counts),
            };
        }

        return $counts;
    }

    /**
     * @param  array{sent: int, deferred: int, failed: int}  $counts
     */
    private function markSent(OutboxEvent $event, array &$counts): void
    {
        $event->update(['status' => OutboxEventStatus::Sent, 'sent_at' => now()]);
        $counts['sent']++;
    }

    /**
     * @param  array{sent: int, deferred: int, failed: int}  $counts
     */
    private function markDeferred(OutboxEvent $event, array &$counts): void
    {
        $event->increment('attempts');
        $counts['deferred']++;
    }

    /**
     * @param  array{sent: int, deferred: int, failed: int}  $counts
     */
    private function markFailed(OutboxEvent $event, array &$counts): void
    {
        $event->update([
            'status' => OutboxEventStatus::Failed,
            'last_error' => 'Ditolak HQ — lihat panel sinkronisasi untuk penyelidikan (CLAUDE.md §8).',
        ]);
        $event->increment('attempts');
        $counts['failed']++;
    }
}
