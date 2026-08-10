<?php

declare(strict_types=1);

namespace App\Application\Services\Sync;

use App\Domain\Sync\Enums\SyncEventOutcome;
use App\Domain\Sync\Exceptions\SyncEventDeferredException;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\ProcessedEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Orkestrasi PENUH satu event masuk (T5.8, dipanggil `SyncEventsController`
 * per event dalam satu batch) — SATU-SATUNYA jalur tulis `processed_events`
 * (ditegakkan `tests/Arch/ProcessedEventSingleWriterTest.php`, pola sama
 * R1/AC-21/T5.7).
 *
 * Setiap event diproses dalam TRANSAKSI SENDIRI (bukan satu transaksi
 * untuk seluruh batch) — supaya SATU event yang `deferred`/`rejected`
 * TIDAK ikut membatalkan event lain dalam batch yang sama yang mungkin
 * tidak saling bergantung (CLAUDE.md §8: pengiriman ulang adalah kondisi
 * normal, batch tidak semuanya-atau-tidak-sama-sekali).
 *
 * Idempotensi mutlak (CLAUDE.md §8): `processed_events` dicek LEBIH DULU
 * sebelum mencoba `SyncEventApplier::apply()` — event yang idnya sudah
 * ada langsung `duplicate`, TANPA memanggil applier sama sekali (aman
 * dipanggil berkali-kali, side effect kedua tidak pernah terjadi).
 */
final class SyncEventProcessor
{
    public function __construct(
        private readonly SyncEventApplier $applier,
    ) {}

    /**
     * @param  array{event_id: string, event_type: string, aggregate_type: string, aggregate_id: string, payload: array<string, mixed>}  $event
     */
    public function process(Branch $sourceBranch, array $event): SyncEventOutcome
    {
        $eventId = $event['event_id'];

        if (ProcessedEvent::query()->whereKey($eventId)->exists()) {
            return SyncEventOutcome::Duplicate;
        }

        try {
            return DB::transaction(function () use ($sourceBranch, $event, $eventId) {
                $this->applier->apply($event['event_type'], $event['payload']);

                ProcessedEvent::create([
                    'id' => $eventId,
                    'branch_id' => $sourceBranch->getKey(),
                    'event_type' => $event['event_type'],
                    'aggregate_type' => $event['aggregate_type'],
                    'aggregate_id' => $event['aggregate_id'],
                    'processed_at' => now(),
                    'created_at' => now(),
                ]);

                return SyncEventOutcome::Accepted;
            });
        } catch (SyncEventDeferredException) {
            return SyncEventOutcome::Deferred;
        } catch (Throwable) {
            // Kegagalan genuine (validasi/tipe data/constraint selain FK) —
            // TIDAK dicatat ke processed_events (lihat docblock migration),
            // cabang menandai `failed` secara lokal dan menampilkannya di
            // panel admin (CLAUDE.md §8), tidak mengirim ulang otomatis.
            return SyncEventOutcome::Rejected;
        }
    }
}
