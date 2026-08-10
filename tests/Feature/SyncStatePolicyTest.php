<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\SyncState;
use App\Infrastructure\Persistence\Policies\SyncStatePolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny/view memerlukan permission manage_settings', function () {
    $policy = new SyncStatePolicy;
    $state = SyncState::factory()->create();

    expect($policy->viewAny(makeTestUser(['manage_settings'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse()
        ->and($policy->view(makeTestUser(['manage_settings']), $state))->toBeTrue();
});

it('create/update/delete/restore/forceDelete selalu ditolak dari sisi pengguna', function () {
    $policy = new SyncStatePolicy;
    $state = SyncState::factory()->create();
    $user = makeTestUser(['manage_settings']);

    expect($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $state))->toBeFalse()
        ->and($policy->delete($user, $state))->toBeFalse()
        ->and($policy->restore($user, $state))->toBeFalse()
        ->and($policy->forceDelete($user, $state))->toBeFalse();
});
