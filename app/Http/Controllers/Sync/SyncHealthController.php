<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\SyncState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/sync/health` (T5.8, CLAUDE.md §8) — "Status node, lag,
 * jumlah tertunda". Dilaporkan dari SUDUT PANDANG HQ tentang cabang YANG
 * MEMANGGIL (diautentikasi via token, `AuthenticateSyncNode`) — HQ tidak
 * pernah tahu langsung berapa `outbox_events` yang MASIH `pending` di
 * database cabang (fisik terpisah, tidak ada koneksi DB lintas node,
 * R6) — `pending_count` di sini adalah `deferred_count` TERAKHIR yang
 * HQ AMATI dari batch terakhir yang diproses (lihat `SyncEventsController`),
 * bukan hitungan real-time.
 *
 * `lag_seconds` — selisih `now()` dengan `last_event_at` (kapan HQ
 * TERAKHIR menerima event dari cabang ini) — indikator paling praktis
 * "seberapa jauh cabang ini tertinggal" tanpa perlu tahu isi outbox
 * cabang secara langsung.
 */
class SyncHealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Branch $branch */
        $branch = $request->attributes->get('syncBranch');

        $state = SyncState::query()->where('branch_id', $branch->getKey())->first();

        return response()->json([
            'node_role' => (string) config('rightclick.node.role'),
            'branch_code' => $branch->code,
            'last_event_at' => $state?->last_event_at?->toIso8601String(),
            'last_seen_at' => $state?->last_seen_at?->toIso8601String(),
            'lag_seconds' => $state?->last_event_at !== null ? (int) now()->diffInSeconds($state->last_event_at, absolute: true) : null,
            'events_processed_count' => $state === null ? 0 : $state->events_processed_count,
            'pending_count' => $state === null ? 0 : $state->deferred_count,
        ]);
    }
}
