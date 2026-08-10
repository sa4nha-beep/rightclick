<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\Unit;
use App\Infrastructure\Persistence\Policies\UnitPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

function makeTestUnit(array $attributes = []): Unit
{
    return Unit::factory()->create($attributes);
}

it('viewAny memerlukan permission view_products', function () {
    $policy = new UnitPolicy;

    expect($policy->viewAny(makeTestUser(['view_products'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view memerlukan permission view_products', function () {
    $policy = new UnitPolicy;
    $unit = makeTestUnit();

    expect($policy->view(makeTestUser(['view_products']), $unit))->toBeTrue()
        ->and($policy->view(makeTestUser(), $unit))->toBeFalse();
});

it('create ditolak tanpa permission create_products', function () {
    $policy = new UnitPolicy;

    expect($policy->create(makeTestUser()))->toBeFalse();
});

it('create mengizinkan permission create_products di node HQ', function () {
    $policy = new UnitPolicy;

    expect($policy->create(makeTestUser(['create_products'])))->toBeTrue();
});

it('create menolak node cabang meski permission tersedia — M02', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    $policy = new UnitPolicy;

    expect($policy->create(makeTestUser(['create_products'])))->toBeFalse();
});

it('update memerlukan permission edit_products dan node HQ', function () {
    $policy = new UnitPolicy;
    $unit = makeTestUnit();
    $authorized = makeTestUser(['edit_products']);

    expect($policy->update($authorized, $unit))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->update($authorized, $unit))->toBeFalse();
});

it('delete memerlukan permission delete_products dan node HQ', function () {
    $policy = new UnitPolicy;
    $unit = makeTestUnit();
    $authorized = makeTestUser(['delete_products']);

    expect($policy->delete($authorized, $unit))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete($authorized, $unit))->toBeFalse();
});

it('restore memerlukan permission delete_products dan node HQ', function () {
    $policy = new UnitPolicy;
    $unit = makeTestUnit();

    expect((new UnitPolicy)->restore(makeTestUser(['delete_products']), $unit))->toBeTrue();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new UnitPolicy;
    $unit = makeTestUnit();
    $owner = makeTestUser(['delete_products']);

    expect($policy->forceDelete($owner, $unit))->toBeFalse();
});
