<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sync;

use App\Application\Services\Sync\SyncEventProcessor;
use App\Domain\Sync\Enums\SyncEventOutcome;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\SyncState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * `POST /api/v1/sync/events` (T5.8, CLAUDE.md §8) — "Kirim batch event
 * dari outbox cabang (maks 500 event, 5 MB)". Diproses SATU PER SATU,
 * masing-masing transaksi sendiri (`SyncEventProcessor::process()`) —
 * lihat docblock kelas itu untuk alasan batch bukan semuanya-atau-tidak.
 *
 * Respons: `{results: [{event_id, status}, ...]}` — status per event,
 * BUKAN satu status untuk seluruh batch. Cabang (`OutboxDispatcher`)
 * menerjemahkan tiap status ke `OutboxEventStatus` lokalnya sendiri.
 */
class SyncEventsController extends Controller
{
    private const MAX_EVENTS_PER_BATCH = 500;

    private const MAX_BATCH_BYTES = 5 * 1024 * 1024;

    public function __invoke(Request $request, SyncEventProcessor $processor): JsonResponse
    {
        /** @var Branch $branch */
        $branch = $request->attributes->get('syncBranch');

        $contentLength = (int) $request->header('Content-Length', '0');

        if ($contentLength > self::MAX_BATCH_BYTES) {
            return response()->json(['message' => 'Ukuran batch melebihi 5 MB.'], 413);
        }

        $validated = Validator::make($request->all(), [
            'events' => ['required', 'array', 'max:'.self::MAX_EVENTS_PER_BATCH],
            'events.*.event_id' => ['required', 'uuid'],
            'events.*.event_type' => ['required', 'string'],
            'events.*.aggregate_type' => ['required', 'string'],
            'events.*.aggregate_id' => ['required', 'uuid'],
            'events.*.payload' => ['required', 'array'],
        ])->validate();

        $results = [];
        $deferredCount = 0;

        foreach ($validated['events'] as $event) {
            $outcome = $processor->process($branch, $event);

            if ($outcome === SyncEventOutcome::Deferred) {
                $deferredCount++;
            }

            $results[] = [
                'event_id' => $event['event_id'],
                'status' => $outcome->value,
            ];
        }

        $lastEvent = $validated['events'][array_key_last($validated['events'])];

        SyncState::query()->updateOrCreate(
            ['branch_id' => $branch->getKey()],
            [
                'last_event_id' => $lastEvent['event_id'],
                'last_event_at' => now(),
                'last_seen_at' => now(),
                'deferred_count' => $deferredCount,
            ],
        );

        SyncState::query()
            ->where('branch_id', $branch->getKey())
            ->increment('events_processed_count', count($results));

        return response()->json(['results' => $results]);
    }
}
