<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\UserBranch;
use App\Infrastructure\Persistence\Policies\UserBranchPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny mengizinkan view_users maupun manage_user_branches', function () {
    $policy = new UserBranchPolicy;

    expect($policy->viewAny(makeTestUser(['view_users'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser(['manage_user_branches'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('create memerlukan permission manage_user_branches dan node HQ', function () {
    $policy = new UserBranchPolicy;
    $authorized = makeTestUser(['manage_user_branches']);

    expect($policy->create($authorized))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->create($authorized))->toBeFalse();
});

it('delete memerlukan permission manage_user_branches dan node HQ', function () {
    $policy = new UserBranchPolicy;
    $user = makeTestUser();
    $branch = makeTestBranch();
    $userBranch = UserBranch::create(['user_id' => $user->id, 'branch_id' => $branch->id]);
    $authorized = makeTestUser(['manage_user_branches']);

    expect($policy->delete($authorized, $userBranch))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete($authorized, $userBranch))->toBeFalse();
});
