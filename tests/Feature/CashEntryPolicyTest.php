<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\CashEntry;
use App\Infrastructure\Persistence\Policies\CashEntryPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny/view memerlukan permission view_cash_entries', function () {
    $policy = new CashEntryPolicy;
    $entry = CashEntry::factory()->create();

    expect($policy->viewAny(makeTestUser(['view_cash_entries'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse()
        ->and($policy->view(makeTestUser(['view_cash_entries']), $entry))->toBeTrue();
});

it('create/update/delete/restore/forceDelete selalu ditolak — hanya CashLedgerService yang menulis', function () {
    $policy = new CashEntryPolicy;
    $entry = CashEntry::factory()->create();
    $user = makeTestUser(['view_cash_entries', 'record_cash_entry']);

    expect($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $entry))->toBeFalse()
        ->and($policy->delete($user, $entry))->toBeFalse()
        ->and($policy->restore($user, $entry))->toBeFalse()
        ->and($policy->forceDelete($user, $entry))->toBeFalse();
});
