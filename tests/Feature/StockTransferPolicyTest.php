<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockTransfer;
use App\Infrastructure\Persistence\Models\StockTransferReceipt;
use App\Infrastructure\Persistence\Policies\StockTransferPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny mengizinkan view_transfer_history maupun perform_transfer', function () {
    $policy = new StockTransferPolicy;

    expect($policy->viewAny(makeTestUser(['view_transfer_history'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser(['perform_transfer'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('dispatch memerlukan perform_transfer dan dokumen draft', function () {
    $policy = new StockTransferPolicy;
    $draft = StockTransfer::factory()->create();
    $final = StockTransfer::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);

    expect($policy->dispatch(makeTestUser(['perform_transfer']), $draft))->toBeTrue()
        ->and($policy->dispatch(makeTestUser(['perform_transfer']), $final))->toBeFalse()
        ->and($policy->dispatch(makeTestUser(), $draft))->toBeFalse();
});

it('void ditolak bila masih ada receipt aktif', function () {
    $policy = new StockTransferPolicy;
    $final = StockTransfer::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);
    StockTransferReceipt::factory()->create([
        'stock_transfer_id' => $final->id,
        'state' => DocumentState::Final,
        'finalized_at' => now(),
    ]);

    expect($policy->void(makeTestUser(['void_stock_document']), $final))->toBeFalse();
});

it('void diizinkan bila belum ada receipt atau receipt sudah void', function () {
    $policy = new StockTransferPolicy;
    $final = StockTransfer::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);

    expect($policy->void(makeTestUser(['void_stock_document']), $final))->toBeTrue();

    $withVoidedReceipt = StockTransfer::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);
    StockTransferReceipt::factory()->create([
        'stock_transfer_id' => $withVoidedReceipt->id,
        'state' => DocumentState::Void,
        'finalized_at' => now(),
        'voided_at' => now(),
        'voided_by' => makeTestUser()->id,
        'void_reason' => 'Uji',
    ]);

    expect($policy->void(makeTestUser(['void_stock_document']), $withVoidedReceipt))->toBeTrue();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new StockTransferPolicy;
    $draft = StockTransfer::factory()->create();

    expect($policy->forceDelete(makeTestUser(['void_stock_document']), $draft))->toBeFalse();
});
