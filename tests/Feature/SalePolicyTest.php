<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Policies\SalePolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('create memerlukan permission create_sale', function () {
    $policy = new SalePolicy;

    expect($policy->create(makeTestUser(['create_sale'])))->toBeTrue()
        ->and($policy->create(makeTestUser()))->toBeFalse();
});

it('finalize digerbang create_sale (bukan edit_sale) — pekerjaan inti Kasir', function () {
    $policy = new SalePolicy;
    $draft = Sale::factory()->create();

    expect($policy->finalize(makeTestUser(['create_sale']), $draft))->toBeTrue()
        ->and($policy->finalize(makeTestUser(['edit_sale']), $draft))->toBeFalse();
});

it('void memerlukan void_sale — permission yang sengaja tidak dimiliki Kasir (§2)', function () {
    $policy = new SalePolicy;
    $final = Sale::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);
    $draft = Sale::factory()->create();

    expect($policy->void(makeTestUser(['void_sale']), $final))->toBeTrue()
        ->and($policy->void(makeTestUser(['create_sale']), $final))->toBeFalse()
        ->and($policy->void(makeTestUser(['void_sale']), $draft))->toBeFalse();
});

it('update/delete memerlukan edit_sale dan dokumen masih draft', function () {
    $policy = new SalePolicy;
    $draft = Sale::factory()->create();
    $final = Sale::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);

    expect($policy->update(makeTestUser(['edit_sale']), $draft))->toBeTrue()
        ->and($policy->update(makeTestUser(['edit_sale']), $final))->toBeFalse()
        ->and($policy->update(makeTestUser(['create_sale']), $draft))->toBeFalse();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new SalePolicy;
    $draft = Sale::factory()->create();

    expect($policy->forceDelete(makeTestUser(['void_sale']), $draft))->toBeFalse();
});

it('approve memerlukan manage_sale_discount dan dokumen draft — terpisah dari decide_approval generik', function () {
    $policy = new SalePolicy;
    $draft = Sale::factory()->create();
    $final = Sale::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);

    expect($policy->approve(makeTestUser(['manage_sale_discount']), $draft))->toBeTrue()
        ->and($policy->approve(makeTestUser(['decide_approval']), $draft))->toBeFalse()
        ->and($policy->approve(makeTestUser(['create_sale']), $draft))->toBeFalse()
        ->and($policy->approve(makeTestUser(['manage_sale_discount']), $final))->toBeFalse();
});
