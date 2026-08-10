<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use App\Infrastructure\Persistence\Policies\PurchaseOrderPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny memerlukan permission view_purchase_orders', function () {
    $policy = new PurchaseOrderPolicy;

    expect($policy->viewAny(makeTestUser(['view_purchase_orders'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('create memerlukan permission create_purchase_order', function () {
    $policy = new PurchaseOrderPolicy;

    expect($policy->create(makeTestUser(['create_purchase_order'])))->toBeTrue()
        ->and($policy->create(makeTestUser()))->toBeFalse();
});

it('update hanya mengizinkan dokumen draft dengan permission edit_purchase_order', function () {
    $policy = new PurchaseOrderPolicy;
    $draft = PurchaseOrder::factory()->create();
    $final = PurchaseOrder::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);

    expect($policy->update(makeTestUser(['edit_purchase_order']), $draft))->toBeTrue()
        ->and($policy->update(makeTestUser(['edit_purchase_order']), $final))->toBeFalse()
        ->and($policy->update(makeTestUser(['create_purchase_order']), $draft))->toBeFalse();
});

it('finalize digerbang create_purchase_order — ambang TH4 adalah logika bisnis, bukan otorisasi', function () {
    $policy = new PurchaseOrderPolicy;
    $draft = PurchaseOrder::factory()->create();

    expect($policy->finalize(makeTestUser(['create_purchase_order']), $draft))->toBeTrue()
        ->and($policy->finalize(makeTestUser(['edit_purchase_order']), $draft))->toBeFalse();
});

it('void memerlukan void_purchase_order dan dokumen final', function () {
    $policy = new PurchaseOrderPolicy;
    $final = PurchaseOrder::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);
    $draft = PurchaseOrder::factory()->create();

    expect($policy->void(makeTestUser(['void_purchase_order']), $final))->toBeTrue()
        ->and($policy->void(makeTestUser(['edit_purchase_order']), $final))->toBeFalse()
        ->and($policy->void(makeTestUser(['void_purchase_order']), $draft))->toBeFalse();
});

it('approve memerlukan approve_purchase_order', function () {
    $policy = new PurchaseOrderPolicy;
    $draft = PurchaseOrder::factory()->create();

    expect($policy->approve(makeTestUser(['approve_purchase_order']), $draft))->toBeTrue()
        ->and($policy->approve(makeTestUser(['decide_approval']), $draft))->toBeFalse()
        ->and($policy->approve(makeTestUser(['create_purchase_order']), $draft))->toBeFalse();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new PurchaseOrderPolicy;
    $draft = PurchaseOrder::factory()->create();

    expect($policy->forceDelete(makeTestUser(['void_purchase_order']), $draft))->toBeFalse();
});
