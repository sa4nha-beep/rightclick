<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Policies\BranchPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny memerlukan permission view_branches', function () {
    $policy = new BranchPolicy;

    expect($policy->viewAny(makeTestUser(['view_branches'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view memerlukan permission view_branches', function () {
    $policy = new BranchPolicy;
    $branch = makeTestBranch();

    expect($policy->view(makeTestUser(['view_branches']), $branch))->toBeTrue()
        ->and($policy->view(makeTestUser(), $branch))->toBeFalse();
});

it('create ditolak tanpa permission create_branches', function () {
    $policy = new BranchPolicy;

    expect($policy->create(makeTestUser()))->toBeFalse();
});

it('create mengizinkan permission create_branches di node HQ', function () {
    $policy = new BranchPolicy;

    expect($policy->create(makeTestUser(['create_branches'])))->toBeTrue();
});

it('create menolak node cabang meski permission tersedia — M02', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    $policy = new BranchPolicy;

    expect($policy->create(makeTestUser(['create_branches'])))->toBeFalse();
});

it('update memerlukan permission edit_branches dan node HQ', function () {
    $policy = new BranchPolicy;
    $branch = makeTestBranch();
    $authorized = makeTestUser(['edit_branches']);

    expect($policy->update($authorized, $branch))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->update($authorized, $branch))->toBeFalse();
});

it('delete memerlukan permission delete_branches dan node HQ', function () {
    $policy = new BranchPolicy;
    $branch = makeTestBranch();
    $authorized = makeTestUser(['delete_branches']);

    expect($policy->delete($authorized, $branch))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete($authorized, $branch))->toBeFalse();
});

it('restore memerlukan permission delete_branches dan node HQ', function () {
    $policy = new BranchPolicy;
    $branch = makeTestBranch();

    expect((new BranchPolicy)->restore(makeTestUser(['delete_branches']), $branch))->toBeTrue();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new BranchPolicy;
    $branch = makeTestBranch();
    $owner = makeTestUser(['delete_branches']);

    expect($policy->forceDelete($owner, $branch))->toBeFalse();
});
