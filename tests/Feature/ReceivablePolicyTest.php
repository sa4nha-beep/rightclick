<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Receivable;
use App\Infrastructure\Persistence\Policies\ReceivablePolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny/view memerlukan permission view_receivables', function () {
    $policy = new ReceivablePolicy;
    $receivable = Receivable::factory()->create();

    expect($policy->viewAny(makeTestUser(['view_receivables'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse()
        ->and($policy->view(makeTestUser(['view_receivables']), $receivable))->toBeTrue()
        ->and($policy->view(makeTestUser(), $receivable))->toBeFalse();
});

it('create/update/delete/restore/forceDelete selalu ditolak — baris cache sistem, tanpa API tulis independen', function () {
    $policy = new ReceivablePolicy;
    $receivable = Receivable::factory()->create();
    $user = makeTestUser(['view_receivables']);

    expect($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $receivable))->toBeFalse()
        ->and($policy->delete($user, $receivable))->toBeFalse()
        ->and($policy->restore($user, $receivable))->toBeFalse()
        ->and($policy->forceDelete($user, $receivable))->toBeFalse();
});
