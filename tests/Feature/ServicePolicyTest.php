<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\Service;
use App\Infrastructure\Persistence\Policies\ServicePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

function makeTestService(array $attributes = []): Service
{
    return Service::factory()->create($attributes);
}

it('viewAny memerlukan permission view_products', function () {
    $policy = new ServicePolicy;

    expect($policy->viewAny(makeTestUser(['view_products'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view memerlukan permission view_products', function () {
    $policy = new ServicePolicy;
    $service = makeTestService();

    expect($policy->view(makeTestUser(['view_products']), $service))->toBeTrue()
        ->and($policy->view(makeTestUser(), $service))->toBeFalse();
});

it('create ditolak tanpa permission create_products', function () {
    $policy = new ServicePolicy;

    expect($policy->create(makeTestUser()))->toBeFalse();
});

it('create mengizinkan permission create_products di node HQ', function () {
    $policy = new ServicePolicy;

    expect($policy->create(makeTestUser(['create_products'])))->toBeTrue();
});

it('create menolak node cabang meski permission tersedia — M02', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    $policy = new ServicePolicy;

    expect($policy->create(makeTestUser(['create_products'])))->toBeFalse();
});

it('update memerlukan permission edit_products dan node HQ', function () {
    $policy = new ServicePolicy;
    $service = makeTestService();
    $authorized = makeTestUser(['edit_products']);

    expect($policy->update($authorized, $service))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->update($authorized, $service))->toBeFalse();
});

it('delete memerlukan permission delete_products dan node HQ', function () {
    $policy = new ServicePolicy;
    $service = makeTestService();
    $authorized = makeTestUser(['delete_products']);

    expect($policy->delete($authorized, $service))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete($authorized, $service))->toBeFalse();
});

it('restore memerlukan permission delete_products dan node HQ', function () {
    $policy = new ServicePolicy;
    $service = makeTestService();

    expect((new ServicePolicy)->restore(makeTestUser(['delete_products']), $service))->toBeTrue();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new ServicePolicy;
    $service = makeTestService();
    $owner = makeTestUser(['delete_products']);

    expect($policy->forceDelete($owner, $service))->toBeFalse();
});

it('price nol atau negatif ditolak database — CHECK constraint', function () {
    expect(fn () => Service::factory()->create(['price' => 0]))
        ->toThrow(QueryException::class);
});
