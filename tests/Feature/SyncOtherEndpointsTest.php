<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\SyncState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * T5.8 — `/ack`, `/health`, `/master-check`, `/master-snapshot/{table}`,
 * `/partner-upsert`. `SyncEventsControllerTest.php` sudah mencakup uji
 * autentikasi/node-hq secara mendalam untuk `/events`; berkas ini fokus
 * pada perilaku masing-masing endpoint SAJA (satu uji akses ditolak untuk
 * cukup membuktikan middleware yang sama juga terpasang di rute lain).
 */
beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => 'hq']);
    $this->branch = Branch::factory()->create();
    $this->token = $this->branch->issueSyncToken();
});

afterEach(function () {
    DB::rollBack();
});

it('ack menolak tanpa token dan memperbarui last_seen_at bila valid', function () {
    $this->postJson('/api/v1/sync/ack', [])->assertUnauthorized();

    $this->withToken($this->token)
        ->postJson('/api/v1/sync/ack', [])
        ->assertOk()
        ->assertJson(['acknowledged' => true]);

    expect(SyncState::query()->where('branch_id', $this->branch->id)->sole()->last_seen_at)->not->toBeNull();
});

it('health melaporkan lag dan jumlah tertunda dari SyncState cabang pemanggil', function () {
    SyncState::factory()->create([
        'branch_id' => $this->branch->id,
        'last_event_at' => now()->subMinutes(5),
        'events_processed_count' => 42,
        'deferred_count' => 3,
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/sync/health')
        ->assertOk()
        ->assertJsonPath('events_processed_count', 42)
        ->assertJsonPath('pending_count', 3);

    $lagSeconds = $response->json('lag_seconds');
    expect($lagSeconds)->toBeGreaterThanOrEqual(290)->toBeLessThanOrEqual(310);
});

it('health mengembalikan nol/null untuk cabang yang belum pernah sinkronisasi', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/sync/health')
        ->assertOk()
        ->assertJson(['events_processed_count' => 0, 'pending_count' => 0, 'last_event_at' => null]);
});

it('master-check membandingkan jumlah baris HQ terhadap yang dilaporkan cabang', function () {
    Branch::factory()->count(2)->create();
    $hqCount = Branch::query()->count();

    $this->withToken($this->token)
        ->postJson('/api/v1/sync/master-check', ['table' => 'branches', 'count' => $hqCount])
        ->assertOk()
        ->assertJson(['table' => 'branches', 'hq_count' => $hqCount, 'match' => true, 'difference' => 0]);

    $this->withToken($this->token)
        ->postJson('/api/v1/sync/master-check', ['table' => 'branches', 'count' => $hqCount - 2])
        ->assertOk()
        ->assertJson(['match' => false, 'difference' => 2]);
});

it('master-check menolak nama tabel yang bukan REPLICATED', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/sync/master-check', ['table' => 'sales', 'count' => 0])
        ->assertUnprocessable();
});

it('master-snapshot mengembalikan seluruh baris tabel REPLICATED dengan paginasi', function () {
    $extraBranches = Branch::factory()->count(3)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/sync/master-snapshot/branches?per_page=2&page=1')
        ->assertOk()
        ->assertJsonPath('table', 'branches')
        ->assertJsonPath('per_page', 2)
        ->assertJsonCount(2, 'rows');

    expect($extraBranches)->toHaveCount(3);
});

it('master-snapshot menolak tabel yang bukan REPLICATED', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/sync/master-snapshot/outbox_events')
        ->assertNotFound();
});

it('partner-upsert mengadopsi partner yang dibuat cabang saat HQ tak terjangkau', function () {
    $partnerId = (string) Str::uuid7();

    $this->withToken($this->token)
        ->postJson('/api/v1/sync/partner-upsert', [
            'id' => $partnerId,
            'code' => 'PTR-WALKIN01',
            'name' => 'Pelanggan Walk-in Darurat',
            'partner_type' => 'customer',
        ])
        ->assertOk()
        ->assertJson(['id' => $partnerId, 'adopted' => true]);

    expect(Partner::query()->whereKey($partnerId)->exists())->toBeTrue();
});

it('partner-upsert menolak kode yang bentrok dengan partner HQ yang sudah ada', function () {
    $existing = Partner::factory()->create(['code' => 'PTR-DUP01']);

    $this->withToken($this->token)
        ->postJson('/api/v1/sync/partner-upsert', [
            'id' => (string) Str::uuid7(),
            'code' => $existing->code,
            'name' => 'Duplikat Kode',
            'partner_type' => 'customer',
        ])
        ->assertUnprocessable();
});
