<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use App\Infrastructure\Persistence\Policies\StockAdjustmentPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny mengizinkan view_stock_mutations maupun perform_adjustment', function () {
    $policy = new StockAdjustmentPolicy;

    expect($policy->viewAny(makeTestUser(['view_stock_mutations'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser(['perform_adjustment'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('create memerlukan permission perform_adjustment', function () {
    $policy = new StockAdjustmentPolicy;

    expect($policy->create(makeTestUser(['perform_adjustment'])))->toBeTrue()
        ->and($policy->create(makeTestUser()))->toBeFalse();
});

it('finalize hanya memerlukan perform_adjustment — ambang adalah logika bisnis, bukan otorisasi', function () {
    $policy = new StockAdjustmentPolicy;
    $draft = StockAdjustment::factory()->create();

    expect($policy->finalize(makeTestUser(['perform_adjustment']), $draft))->toBeTrue()
        ->and($policy->finalize(makeTestUser(), $draft))->toBeFalse();
});

it('void memerlukan void_stock_document dan dokumen final', function () {
    $policy = new StockAdjustmentPolicy;
    $final = StockAdjustment::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);
    $draft = StockAdjustment::factory()->create();

    expect($policy->void(makeTestUser(['void_stock_document']), $final))->toBeTrue()
        ->and($policy->void(makeTestUser(['perform_adjustment']), $final))->toBeFalse()
        ->and($policy->void(makeTestUser(['void_stock_document']), $draft))->toBeFalse();
});

it('approve memerlukan approve_stock_adjustment — terpisah dari decide_approval generik', function () {
    $policy = new StockAdjustmentPolicy;
    $draft = StockAdjustment::factory()->create();

    expect($policy->approve(makeTestUser(['approve_stock_adjustment']), $draft))->toBeTrue()
        ->and($policy->approve(makeTestUser(['decide_approval']), $draft))->toBeFalse()
        ->and($policy->approve(makeTestUser(['perform_adjustment']), $draft))->toBeFalse();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new StockAdjustmentPolicy;
    $draft = StockAdjustment::factory()->create();

    expect($policy->forceDelete(makeTestUser(['void_stock_document']), $draft))->toBeFalse();
});
