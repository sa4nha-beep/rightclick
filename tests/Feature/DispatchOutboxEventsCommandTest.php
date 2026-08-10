<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('sukses tanpa efek samping bila sync belum dikonfigurasi', function () {
    config(['rightclick.sync.hq_url' => null, 'rightclick.sync.token' => null]);

    $this->artisan('sync:dispatch')->assertSuccessful();
});

it('mengirim batch dan melaporkan ringkasan hasil', function () {
    config(['rightclick.sync.hq_url' => 'https://hq.test', 'rightclick.sync.token' => 'token-uji']);
    $branch = Branch::factory()->create();
    $event = OutboxEvent::factory()->create(['branch_id' => $branch->id]);

    Http::fake([
        'https://hq.test/api/v1/sync/events' => Http::response([
            'results' => [['event_id' => $event->id, 'status' => 'accepted']],
        ]),
    ]);

    $this->artisan('sync:dispatch')
        ->expectsOutputToContain('terkirim: 1')
        ->assertSuccessful();
});
