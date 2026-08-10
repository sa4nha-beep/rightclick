<?php

declare(strict_types=1);

use App\Domain\Inventory\Enums\StockOpnameType;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockOpname;
use App\Infrastructure\Persistence\Policies\StockOpnamePolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny mengizinkan view_stock_mutations maupun perform_opname', function () {
    $policy = new StockOpnamePolicy;

    expect($policy->viewAny(makeTestUser(['view_stock_mutations'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser(['perform_opname'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('create memerlukan permission perform_opname', function () {
    $policy = new StockOpnamePolicy;

    expect($policy->create(makeTestUser(['perform_opname'])))->toBeTrue()
        ->and($policy->create(makeTestUser()))->toBeFalse();
});

it('update/delete hanya untuk dokumen draft', function () {
    $policy = new StockOpnamePolicy;
    $user = makeTestUser(['perform_opname']);
    $draft = StockOpname::factory()->create();
    $final = StockOpname::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);

    expect($policy->update($user, $draft))->toBeTrue()
        ->and($policy->update($user, $final))->toBeFalse()
        ->and($policy->delete($user, $draft))->toBeTrue()
        ->and($policy->delete($user, $final))->toBeFalse();
});

it('finalize opname berkala hanya memerlukan perform_opname', function () {
    $policy = new StockOpnamePolicy;
    $draft = StockOpname::factory()->create(['type' => StockOpnameType::Periodic]);

    expect($policy->finalize(makeTestUser(['perform_opname']), $draft))->toBeTrue()
        ->and($policy->finalize(makeTestUser(), $draft))->toBeFalse();
});

it('finalize opname saldo awal (R9) memerlukan adjust_opening_balance TAMBAHAN', function () {
    $policy = new StockOpnamePolicy;
    $draft = StockOpname::factory()->create(['type' => StockOpnameType::OpeningBalance]);

    expect($policy->finalize(makeTestUser(['perform_opname']), $draft))->toBeFalse()
        ->and($policy->finalize(makeTestUser(['perform_opname', 'adjust_opening_balance']), $draft))->toBeTrue();
});

it('void memerlukan permission void_stock_document dan dokumen final', function () {
    $policy = new StockOpnamePolicy;
    $final = StockOpname::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);
    $draft = StockOpname::factory()->create();

    expect($policy->void(makeTestUser(['void_stock_document']), $final))->toBeTrue()
        ->and($policy->void(makeTestUser(['perform_opname']), $final))->toBeFalse()
        ->and($policy->void(makeTestUser(['void_stock_document']), $draft))->toBeFalse();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new StockOpnamePolicy;
    $draft = StockOpname::factory()->create();

    expect($policy->forceDelete(makeTestUser(['void_stock_document']), $draft))->toBeFalse();
});
