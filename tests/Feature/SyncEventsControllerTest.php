<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use App\Infrastructure\Persistence\Models\ProcessedEvent;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\SyncState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * T5.8 — `POST /api/v1/sync/events`, uji HTTP nyata (bukan `Livewire::test()`
 * yang melewati middleware — pola sama `ShowSaleReceiptControllerTest`
 * dari T4.4). Route hanya aktif saat `rightclick.node.role=hq`
 * (`EnsureNodeIsHq`).
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

it('menolak permintaan tanpa token', function () {
    $this->postJson('/api/v1/sync/events', ['events' => []])
        ->assertUnauthorized();
});

it('menolak token yang tidak valid', function () {
    $this->withToken('token-salah')
        ->postJson('/api/v1/sync/events', ['events' => []])
        ->assertUnauthorized();
});

it('menolak akses saat node BUKAN hq', function () {
    config(['rightclick.node.role' => 'branch']);

    $this->withToken($this->token)
        ->postJson('/api/v1/sync/events', ['events' => []])
        ->assertNotFound();
});

it('memproses batch event dan mengembalikan status per event — accepted lalu duplicate saat dikirim ulang', function () {
    $product = Product::factory()->create();
    $user = makeTestUser(['create_sale']);
    $this->actingAs($user);

    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $product, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $shift = CashierShift::factory()->create(['branch_id' => $this->branch->id, 'cashier_id' => $user->id]);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $shift->id]);
    $sale->items()->create(['product_id' => $product->id, 'quantity' => '1.0000', 'unit_price' => '15000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '15000.00']);
    $finalized = app(FinalizeSaleAction::class)->execute($sale);

    $event = OutboxEvent::query()->where('aggregate_id', $finalized->id)->orderByDesc('id')->firstOrFail();

    $body = [
        'events' => [[
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'aggregate_type' => $event->aggregate_type,
            'aggregate_id' => $event->aggregate_id,
            'payload' => $event->payload,
        ]],
    ];

    $this->withToken($this->token)
        ->postJson('/api/v1/sync/events', $body)
        ->assertOk()
        ->assertJson(['results' => [['event_id' => $event->id, 'status' => 'accepted']]]);

    expect(ProcessedEvent::query()->whereKey($event->id)->exists())->toBeTrue();

    $syncState = SyncState::query()->where('branch_id', $this->branch->id)->sole();
    expect($syncState->events_processed_count)->toBe(1)
        ->and($syncState->last_event_id)->toBe($event->id);

    // Kirim ulang batch YANG SAMA — kondisi normal (CLAUDE.md §8), bukan
    // kegagalan.
    $this->withToken($this->token)
        ->postJson('/api/v1/sync/events', $body)
        ->assertOk()
        ->assertJson(['results' => [['event_id' => $event->id, 'status' => 'duplicate']]]);
});

it('menolak batch melebihi 500 event lewat validasi', function () {
    $events = array_fill(0, 501, [
        'event_id' => (string) Str::uuid7(),
        'event_type' => 'sale.finalized',
        'aggregate_type' => 'sale',
        'aggregate_id' => (string) Str::uuid7(),
        'payload' => ['id' => (string) Str::uuid7()],
    ]);

    $this->withToken($this->token)
        ->postJson('/api/v1/sync/events', ['events' => $events])
        ->assertUnprocessable();
});
