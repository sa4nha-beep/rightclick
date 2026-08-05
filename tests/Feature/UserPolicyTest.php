<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Policies\UserPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny memerlukan permission view_users', function () {
    $policy = new UserPolicy;

    expect($policy->viewAny(makeTestUser(['view_users'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view mengizinkan pengguna melihat profil sendiri tanpa permission', function () {
    $policy = new UserPolicy;
    $self = makeTestUser();

    expect($policy->view($self, $self))->toBeTrue();
});

it('view menolak melihat pengguna lain tanpa permission view_users', function () {
    $policy = new UserPolicy;

    expect($policy->view(makeTestUser(), makeTestUser()))->toBeFalse();
});

it('create memerlukan permission create_users dan node HQ', function () {
    $policy = new UserPolicy;
    $authorized = makeTestUser(['create_users']);

    expect($policy->create($authorized))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->create($authorized))->toBeFalse();
});

it('update memerlukan permission edit_users dan node HQ', function () {
    $policy = new UserPolicy;
    $target = makeTestUser();
    $authorized = makeTestUser(['edit_users']);

    expect($policy->update($authorized, $target))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->update($authorized, $target))->toBeFalse();
});

it('delete memerlukan permission delete_users dan node HQ', function () {
    $policy = new UserPolicy;
    $target = makeTestUser();
    $owner = makeTestUser(['delete_users']);

    expect($policy->delete($owner, $target))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete($owner, $target))->toBeFalse();
});

it('delete menolak pengguna menghapus akunnya sendiri', function () {
    $policy = new UserPolicy;
    $self = makeTestUser(['delete_users']);

    expect($policy->delete($self, $self))->toBeFalse();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new UserPolicy;
    $target = makeTestUser();
    $owner = makeTestUser(['delete_users']);

    expect($policy->forceDelete($owner, $target))->toBeFalse();
});

it('manageRoles memerlukan permission manage_user_roles dan node HQ', function () {
    $policy = new UserPolicy;
    $target = makeTestUser();
    $authorized = makeTestUser(['manage_user_roles']);

    expect($policy->manageRoles($authorized, $target))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->manageRoles($authorized, $target))->toBeFalse();
});

it('manageBranches memerlukan permission manage_user_branches dan node HQ', function () {
    $policy = new UserPolicy;
    $target = makeTestUser();
    $authorized = makeTestUser(['manage_user_branches']);

    expect($policy->manageBranches($authorized, $target))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->manageBranches($authorized, $target))->toBeFalse();
});

it('manageEmergencyDisable bekerja di node cabang tanpa HQ — darurat offline', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    $policy = new UserPolicy;
    $target = makeTestUser();
    $authorized = makeTestUser(['manage_emergency_disable']);

    expect($policy->manageEmergencyDisable($authorized, $target))->toBeTrue();
});

it('manageEmergencyDisable tetap memerlukan permission meski di node cabang', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    $policy = new UserPolicy;
    $target = makeTestUser();

    expect($policy->manageEmergencyDisable(makeTestUser(), $target))->toBeFalse();
});
