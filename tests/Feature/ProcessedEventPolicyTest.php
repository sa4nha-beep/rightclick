<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\ProcessedEvent;
use App\Infrastructure\Persistence\Policies\ProcessedEventPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny/view memerlukan permission manage_settings', function () {
    $policy = new ProcessedEventPolicy;
    $event = ProcessedEvent::factory()->create();

    expect($policy->viewAny(makeTestUser(['manage_settings'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse()
        ->and($policy->view(makeTestUser(['manage_settings']), $event))->toBeTrue();
});

it('create/update/delete/restore/forceDelete selalu ditolak — hanya SyncEventProcessor yang menulis', function () {
    $policy = new ProcessedEventPolicy;
    $event = ProcessedEvent::factory()->create();
    $user = makeTestUser(['manage_settings']);

    expect($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $event))->toBeFalse()
        ->and($policy->delete($user, $event))->toBeFalse()
        ->and($policy->restore($user, $event))->toBeFalse()
        ->and($policy->forceDelete($user, $event))->toBeFalse();
});
