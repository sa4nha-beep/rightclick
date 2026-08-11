<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Payable;
use App\Infrastructure\Persistence\Policies\PayablePolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny/view memerlukan permission view_payables', function () {
    $policy = new PayablePolicy;
    $payable = Payable::factory()->create();

    expect($policy->viewAny(makeTestUser(['view_payables'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse()
        ->and($policy->view(makeTestUser(['view_payables']), $payable))->toBeTrue()
        ->and($policy->view(makeTestUser(), $payable))->toBeFalse();
});

it('create/update/delete/restore/forceDelete selalu ditolak — baris cache sistem, tanpa API tulis independen', function () {
    $policy = new PayablePolicy;
    $payable = Payable::factory()->create();
    $user = makeTestUser(['view_payables']);

    expect($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $payable))->toBeFalse()
        ->and($policy->delete($user, $payable))->toBeFalse()
        ->and($policy->restore($user, $payable))->toBeFalse()
        ->and($policy->forceDelete($user, $payable))->toBeFalse();
});
