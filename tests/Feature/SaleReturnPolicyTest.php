<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\SaleReturn;
use App\Infrastructure\Persistence\Policies\SaleReturnPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('create memerlukan permission create_sale_return', function () {
    $policy = new SaleReturnPolicy;

    expect($policy->create(makeTestUser(['create_sale_return'])))->toBeTrue()
        ->and($policy->create(makeTestUser()))->toBeFalse();
});

it('finalize memerlukan process_sale_return — TIDAK dimiliki Kasir (kontrol anti-fraud)', function () {
    $policy = new SaleReturnPolicy;
    $draft = SaleReturn::factory()->create();

    expect($policy->finalize(makeTestUser(['process_sale_return']), $draft))->toBeTrue()
        ->and($policy->finalize(makeTestUser(['create_sale_return']), $draft))->toBeFalse();
});

it('void memerlukan process_sale_return dan dokumen final', function () {
    $policy = new SaleReturnPolicy;
    $final = SaleReturn::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);
    $draft = SaleReturn::factory()->create();

    expect($policy->void(makeTestUser(['process_sale_return']), $final))->toBeTrue()
        ->and($policy->void(makeTestUser(['process_sale_return']), $draft))->toBeFalse();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new SaleReturnPolicy;
    $draft = SaleReturn::factory()->create();

    expect($policy->forceDelete(makeTestUser(['process_sale_return']), $draft))->toBeFalse();
});
