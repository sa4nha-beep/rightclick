<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\PurchasePaymentAllocation;
use App\Infrastructure\Persistence\Policies\PurchasePaymentAllocationPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny/view memerlukan permission view_payables', function () {
    $policy = new PurchasePaymentAllocationPolicy;
    $allocation = PurchasePaymentAllocation::factory()->create();

    expect($policy->viewAny(makeTestUser(['view_payables'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse()
        ->and($policy->view(makeTestUser(['view_payables']), $allocation))->toBeTrue();
});

it('create/update/delete/restore/forceDelete selalu ditolak — tanpa API tulis independen di luar RecordPurchasePaymentAction', function () {
    $policy = new PurchasePaymentAllocationPolicy;
    $allocation = PurchasePaymentAllocation::factory()->create();
    $user = makeTestUser(['view_payables']);

    expect($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $allocation))->toBeFalse()
        ->and($policy->delete($user, $allocation))->toBeFalse()
        ->and($policy->restore($user, $allocation))->toBeFalse()
        ->and($policy->forceDelete($user, $allocation))->toBeFalse();
});
