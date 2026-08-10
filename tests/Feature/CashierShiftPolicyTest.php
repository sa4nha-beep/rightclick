<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Policies\CashierShiftPolicy;
use App\Infrastructure\Persistence\Support\BranchContext;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('create memerlukan close_cashier_shift — direuse untuk seluruh siklus shift', function () {
    $policy = new CashierShiftPolicy;
    $user = makeTestUser(['close_cashier_shift']);
    app(BranchContext::class)->set($user->default_branch_id);

    expect($policy->create($user))->toBeTrue()
        ->and($policy->create(makeTestUser()))->toBeFalse();
});

it('create ditolak bila aktor sudah punya shift draft terbuka di cabang yang sama', function () {
    $policy = new CashierShiftPolicy;
    $user = makeTestUser(['close_cashier_shift']);
    app(BranchContext::class)->set($user->default_branch_id);

    CashierShift::factory()->create([
        'branch_id' => $user->default_branch_id,
        'cashier_id' => $user->id,
    ]);

    expect($policy->create($user))->toBeFalse();
});

it('void digerbang void_sale (direuse dari permission penjualan) — Kasir tidak memilikinya', function () {
    $policy = new CashierShiftPolicy;
    $final = CashierShift::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);
    $draft = CashierShift::factory()->create();

    expect($policy->void(makeTestUser(['void_sale']), $final))->toBeTrue()
        ->and($policy->void(makeTestUser(['close_cashier_shift']), $final))->toBeFalse()
        ->and($policy->void(makeTestUser(['void_sale']), $draft))->toBeFalse();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new CashierShiftPolicy;
    $draft = CashierShift::factory()->create();

    expect($policy->forceDelete(makeTestUser(['void_sale']), $draft))->toBeFalse();
});
