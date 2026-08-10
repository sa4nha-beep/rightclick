<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Policies\StockBatchPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny memerlukan permission view_batches', function () {
    $policy = new StockBatchPolicy;

    expect($policy->viewAny(makeTestUser(['view_batches'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view memerlukan permission view_batches', function () {
    $policy = new StockBatchPolicy;
    $batch = StockBatch::factory()->create();

    expect($policy->view(makeTestUser(['view_batches']), $batch))->toBeTrue()
        ->and($policy->view(makeTestUser(), $batch))->toBeFalse();
});

it('create/update/delete/restore/forceDelete selalu ditolak — hanya StockLedgerService yang menulis (R1)', function () {
    $policy = new StockBatchPolicy;
    $batch = StockBatch::factory()->create();
    $owner = makeTestUser(['view_batches', 'perform_opname', 'perform_adjustment', 'perform_transfer']);

    expect($policy->create($owner))->toBeFalse()
        ->and($policy->update($owner, $batch))->toBeFalse()
        ->and($policy->delete($owner, $batch))->toBeFalse()
        ->and($policy->restore($owner, $batch))->toBeFalse()
        ->and($policy->forceDelete($owner, $batch))->toBeFalse();
});
