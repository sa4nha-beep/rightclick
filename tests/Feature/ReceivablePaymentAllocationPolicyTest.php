<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\ReceivablePaymentAllocation;
use App\Infrastructure\Persistence\Policies\ReceivablePaymentAllocationPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny/view memerlukan permission view_receivables', function () {
    $policy = new ReceivablePaymentAllocationPolicy;
    $allocation = ReceivablePaymentAllocation::factory()->create();

    expect($policy->viewAny(makeTestUser(['view_receivables'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse()
        ->and($policy->view(makeTestUser(['view_receivables']), $allocation))->toBeTrue();
});

it('create/update/delete/restore/forceDelete selalu ditolak — tanpa API tulis independen di luar RecordReceivablePaymentAction', function () {
    $policy = new ReceivablePaymentAllocationPolicy;
    $allocation = ReceivablePaymentAllocation::factory()->create();
    $user = makeTestUser(['view_receivables']);

    expect($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $allocation))->toBeFalse()
        ->and($policy->delete($user, $allocation))->toBeFalse()
        ->and($policy->restore($user, $allocation))->toBeFalse()
        ->and($policy->forceDelete($user, $allocation))->toBeFalse();
});
