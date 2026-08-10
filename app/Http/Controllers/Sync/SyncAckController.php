<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\SyncState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /api/v1/sync/ack` (T5.8, CLAUDE.md §8) — "Konfirmasi batch
 * diproses". `POST /sync/events` (`SyncEventsController`) SUDAH
 * mengembalikan status FINAL per event secara SINKRON dalam responsnya
 * sendiri — endpoint ini TIDAK mengubah status event apa pun (itu sudah
 * final saat `/sync/events` merespons).
 *
 * Perannya murni KONFIRMASI LIVENESS: cabang memberi tahu HQ "respons
 * batch sudah diterima dan status lokal (`outbox_events`) sudah
 * diperbarui sesuai" — memperbarui `sync_states.last_seen_at` sebagai
 * bukti jalur PULANG (HQ → cabang) juga sehat, melengkapi `last_event_at`
 * yang hanya membuktikan jalur PERGI (cabang → HQ).
 */
class SyncAckController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Branch $branch */
        $branch = $request->attributes->get('syncBranch');

        SyncState::query()->updateOrCreate(
            ['branch_id' => $branch->getKey()],
            ['last_seen_at' => now()],
        );

        return response()->json(['acknowledged' => true]);
    }
}
