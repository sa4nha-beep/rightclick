<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Domain\Shared\Enums\PartnerType;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Policies\PartnerPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

function makeTestPartner(array $attributes = []): Partner
{
    return Partner::factory()->create($attributes);
}

it('viewAny memerlukan permission view_partners', function () {
    $policy = new PartnerPolicy;

    expect($policy->viewAny(makeTestUser(['view_partners'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view memerlukan permission view_partners', function () {
    $policy = new PartnerPolicy;
    $partner = makeTestPartner();

    expect($policy->view(makeTestUser(['view_partners']), $partner))->toBeTrue()
        ->and($policy->view(makeTestUser(), $partner))->toBeFalse();
});

it('create ditolak tanpa permission create_partners', function () {
    $policy = new PartnerPolicy;

    expect($policy->create(makeTestUser()))->toBeFalse();
});

it('create mengizinkan permission create_partners di node HQ', function () {
    $policy = new PartnerPolicy;

    expect($policy->create(makeTestUser(['create_partners'])))->toBeTrue();
});

it('create menolak node cabang meski permission tersedia — M02', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    $policy = new PartnerPolicy;

    expect($policy->create(makeTestUser(['create_partners'])))->toBeFalse();
});

it('update memerlukan permission edit_partners dan node HQ', function () {
    $policy = new PartnerPolicy;
    $partner = makeTestPartner();
    $authorized = makeTestUser(['edit_partners']);

    expect($policy->update($authorized, $partner))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->update($authorized, $partner))->toBeFalse();
});

it('delete memerlukan permission delete_partners dan node HQ', function () {
    $policy = new PartnerPolicy;
    $partner = makeTestPartner();
    $authorized = makeTestUser(['delete_partners']);

    expect($policy->delete($authorized, $partner))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete($authorized, $partner))->toBeFalse();
});

it('restore memerlukan permission delete_partners dan node HQ', function () {
    $policy = new PartnerPolicy;
    $partner = makeTestPartner();

    expect((new PartnerPolicy)->restore(makeTestUser(['delete_partners']), $partner))->toBeTrue();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new PartnerPolicy;
    $partner = makeTestPartner();
    $owner = makeTestUser(['delete_partners']);

    expect($policy->forceDelete($owner, $partner))->toBeFalse();
});

it('partner_type dipetakan ke PartnerType enum', function () {
    $partner = makeTestPartner(['partner_type' => 'both']);

    expect($partner->partner_type)->toBe(PartnerType::Both);
});
