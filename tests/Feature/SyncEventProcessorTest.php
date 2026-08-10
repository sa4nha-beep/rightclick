<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Services\StockLedgerService;
use App\Application\Services\Sync\SyncEventProcessor;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Sync\Enums\SyncEventOutcome;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashEntry;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use App\Infrastructure\Persistence\Models\ProcessedEvent;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\SaleItem;
use App\Infrastructure\Persistence\Models\SalePayment;
use App\Infrastructure\Persistence\Models\StockMutation;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * T5.8, simpul kritis — SyncEventProcessor/SyncEventApplier. Membuktikan
 * REKONSTRUKSI NYATA di sisi HQ (bukan sekadar no-op idempoten): baris
 * `sales`/`sale_items`/`sale_payments`/`stock_mutations`/`stock_batches`/
 * `cash_entries` DIHAPUS dulu (mensimulasikan "data ini ada di cabang,
 * BELUM ada di HQ" — dev environment ini satu database fisik, jadi
 * simulasi eksplisit diperlukan; lihat catatan CashLedgerConcurrencyTest/
 * StockLedgerConcurrencyTest untuk pola serupa "buktikan mekanisme
 * sungguhan, bukan simulasi" di simpul kritis lain), baru payload event
 * yang SAMA diterapkan ulang dan diverifikasi baris-baris itu benar-benar
 * MUNCUL KEMBALI dari payload semata.
 */
beforeEach(function () {
    DB::beginTransaction();
    $this->processor = app(SyncEventProcessor::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->user = makeTestUser(['create_sale']);
    $this->actingAs($this->user);
});

afterEach(function () {
    DB::rollBack();
});

function makeFinalizedSaleOutboxPayload(Branch $branch, Product $product, $user): array
{
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $branch, $product, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $shift = CashierShift::factory()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $sale = Sale::factory()->create(['branch_id' => $branch->id, 'cashier_shift_id' => $shift->id]);
    $sale->items()->create(['product_id' => $product->id, 'quantity' => '1.0000', 'unit_price' => '15000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '15000.00']);

    $finalized = app(FinalizeSaleAction::class)->execute($sale);

    $event = OutboxEvent::query()
        ->where('aggregate_type', $finalized->getMorphClass())
        ->where('aggregate_id', $finalized->id)
        ->orderByDesc('id')
        ->firstOrFail();

    return [
        'event_id' => $event->id,
        'event_type' => $event->event_type,
        'aggregate_type' => $event->aggregate_type,
        'aggregate_id' => $event->aggregate_id,
        'payload' => $event->payload,
    ];
}

it('accepted — merekonstruksi seluruh baris SYNCED dari payload setelah dihapus, membuktikan sinkronisasi nyata bukan no-op', function () {
    $event = makeFinalizedSaleOutboxPayload($this->branch, $this->product, $this->user);
    $saleId = $event['aggregate_id'];

    // Simulasikan "belum pernah sampai HQ" — hapus seluruh baris SYNCED
    // yang tercipta dari finalisasi ini (urutan mundur untuk FK).
    StockMutation::withoutGlobalScope(BranchScope::class)
        ->where('reference_type', $event['aggregate_type'])->where('reference_id', $saleId)->delete();
    CashEntry::withoutGlobalScope(BranchScope::class)
        ->where('reference_type', $event['aggregate_type'])->where('reference_id', $saleId)->delete();
    SaleItem::query()->where('sale_id', $saleId)->delete();
    SalePayment::query()->where('sale_id', $saleId)->delete();
    // forceDelete() (BUKAN delete()/soft delete) — Sale harus benar-benar
    // TIDAK ADA secara fisik, supaya reconstruction dari payload teruji
    // sungguhan, bukan cuma "upsert menimpa baris soft-deleted yang masih
    // ada".
    Sale::withoutGlobalScope(BranchScope::class)->whereKey($saleId)->forceDelete();

    expect(Sale::withoutGlobalScope(BranchScope::class)->whereKey($saleId)->exists())->toBeFalse();

    $outcome = $this->processor->process($this->branch, $event);

    expect($outcome)->toBe(SyncEventOutcome::Accepted);

    $reconstructed = Sale::withoutGlobalScope(BranchScope::class)->findOrFail($saleId);
    expect((string) $reconstructed->total_amount)->toEqual('15000.00')
        ->and(SaleItem::where('sale_id', $saleId)->count())->toBe(1)
        ->and(SalePayment::where('sale_id', $saleId)->count())->toBe(1)
        ->and(StockMutation::withoutGlobalScope(BranchScope::class)->where('reference_id', $saleId)->count())->toBe(1)
        ->and(CashEntry::withoutGlobalScope(BranchScope::class)->where('reference_id', $saleId)->count())->toBe(1);

    expect(ProcessedEvent::query()->whereKey($event['event_id'])->exists())->toBeTrue();
});

it('duplicate — event yang sudah tercatat di processed_events tidak diproses ulang', function () {
    $event = makeFinalizedSaleOutboxPayload($this->branch, $this->product, $this->user);

    $first = $this->processor->process($this->branch, $event);
    expect($first)->toBe(SyncEventOutcome::Accepted);

    $second = $this->processor->process($this->branch, $event);
    expect($second)->toBe(SyncEventOutcome::Duplicate);

    expect(ProcessedEvent::query()->where('id', $event['event_id'])->count())->toBe(1);
});

it('deferred — dokumen bergantung pada entitas yang belum tiba di HQ (FK violation), tidak ada baris yang tersisa (rollback)', function () {
    $event = makeFinalizedSaleOutboxPayload($this->branch, $this->product, $this->user);
    $saleId = $event['aggregate_id'];
    $shiftId = $event['payload']['cashier_shift_id'];

    // Hapus SELURUH jejak Sale ini (urutan FK: anak dulu, baru induk), lalu
    // cashier_shifts JUGA dihapus fisik — mensimulasikan shift yang belum
    // tiba di HQ (cashier_shift.finalized belum diproses). forceDelete()
    // (BUKAN delete()/soft delete) — baris HARUS benar-benar tidak ada
    // secara fisik supaya FK constraint sungguhan terlanggar.
    StockMutation::withoutGlobalScope(BranchScope::class)
        ->where('reference_type', $event['aggregate_type'])->where('reference_id', $saleId)->delete();
    CashEntry::withoutGlobalScope(BranchScope::class)
        ->where('reference_type', $event['aggregate_type'])->where('reference_id', $saleId)->delete();
    SaleItem::query()->where('sale_id', $saleId)->delete();
    SalePayment::query()->where('sale_id', $saleId)->delete();
    Sale::withoutGlobalScope(BranchScope::class)->whereKey($saleId)->forceDelete();
    CashierShift::withoutGlobalScope(BranchScope::class)->whereKey($shiftId)->forceDelete();

    $outcome = $this->processor->process($this->branch, $event);

    expect($outcome)->toBe(SyncEventOutcome::Deferred)
        ->and(Sale::withoutGlobalScope(BranchScope::class)->whereKey($saleId)->exists())->toBeFalse()
        ->and(ProcessedEvent::query()->whereKey($event['event_id'])->exists())->toBeFalse();
});

it('rejected — event_type tidak dikenal registry, tidak tercatat di processed_events', function () {
    $outcome = $this->processor->process($this->branch, [
        'event_id' => (string) Str::uuid7(),
        'event_type' => 'unknown_document.finalized',
        'aggregate_type' => 'unknown',
        'aggregate_id' => (string) Str::uuid7(),
        'payload' => ['id' => (string) Str::uuid7()],
    ]);

    expect($outcome)->toBe(SyncEventOutcome::Rejected);
});
