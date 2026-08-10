<?php

declare(strict_types=1);

use App\Application\Services\Sync\OutboxDispatcher;
use App\Domain\Sync\Enums\OutboxEventStatus;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * T5.8 — `OutboxDispatcher` (sisi cabang). `Http::fake()` mensimulasikan
 * respons HQ tanpa panggilan jaringan sungguhan — kebenaran sisi PENERIMA
 * (HQ) sudah dibuktikan terpisah lewat `SyncEventsControllerTest`/
 * `SyncEventProcessorTest`; berkas ini membuktikan dispatcher
 * MENERJEMAHKAN respons ke `OutboxEventStatus` lokal dengan benar.
 */
beforeEach(function () {
    DB::beginTransaction();
    $this->branch = Branch::factory()->create();
});

afterEach(function () {
    DB::rollBack();
});

it('tidak melakukan apa pun bila hq_url/token belum dikonfigurasi', function () {
    $dispatcher = new OutboxDispatcher(hqUrl: null, token: null);

    $result = $dispatcher->dispatch();

    expect($result)->toBe(['sent' => 0, 'deferred' => 0, 'failed' => 0]);
});

it('tidak melakukan apa pun bila tidak ada outbox_events pending', function () {
    $dispatcher = new OutboxDispatcher(hqUrl: 'https://hq.test', token: 'token-uji');

    $result = $dispatcher->dispatch();

    expect($result)->toBe(['sent' => 0, 'deferred' => 0, 'failed' => 0]);
});

it('menerjemahkan accepted/duplicate menjadi Sent, deferred tetap Pending, rejected menjadi Failed', function () {
    $accepted = OutboxEvent::factory()->create(['branch_id' => $this->branch->id]);
    $duplicate = OutboxEvent::factory()->create(['branch_id' => $this->branch->id]);
    $deferred = OutboxEvent::factory()->create(['branch_id' => $this->branch->id]);
    $rejected = OutboxEvent::factory()->create(['branch_id' => $this->branch->id]);

    Http::fake([
        'https://hq.test/api/v1/sync/events' => Http::response([
            'results' => [
                ['event_id' => $accepted->id, 'status' => 'accepted'],
                ['event_id' => $duplicate->id, 'status' => 'duplicate'],
                ['event_id' => $deferred->id, 'status' => 'deferred'],
                ['event_id' => $rejected->id, 'status' => 'rejected'],
            ],
        ]),
    ]);

    $dispatcher = new OutboxDispatcher(hqUrl: 'https://hq.test', token: 'token-uji');
    $result = $dispatcher->dispatch();

    expect($result)->toBe(['sent' => 2, 'deferred' => 1, 'failed' => 1]);

    expect($accepted->fresh()->status)->toBe(OutboxEventStatus::Sent)
        ->and($accepted->fresh()->sent_at)->not->toBeNull()
        ->and($duplicate->fresh()->status)->toBe(OutboxEventStatus::Sent)
        ->and($deferred->fresh()->status)->toBe(OutboxEventStatus::Pending)
        ->and($deferred->fresh()->attempts)->toBe(1)
        ->and($rejected->fresh()->status)->toBe(OutboxEventStatus::Failed)
        ->and($rejected->fresh()->last_error)->not->toBeNull();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://hq.test/api/v1/sync/events'
            && $request->hasHeader('Authorization', 'Bearer token-uji')
            && count($request->data()['events']) === 4;
    });
});

it('mengabaikan kegagalan jaringan/HQ — event tetap pending, tidak melempar exception (R8)', function () {
    OutboxEvent::factory()->create(['branch_id' => $this->branch->id]);

    Http::fake([
        'https://hq.test/api/v1/sync/events' => Http::response(['message' => 'Server Error'], 500),
    ]);

    $dispatcher = new OutboxDispatcher(hqUrl: 'https://hq.test', token: 'token-uji');
    $result = $dispatcher->dispatch();

    expect($result)->toBe(['sent' => 0, 'deferred' => 0, 'failed' => 0])
        ->and(OutboxEvent::query()->where('status', OutboxEventStatus::Pending->value)->count())->toBe(1);
});
