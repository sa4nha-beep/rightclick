<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Policies\GoodsReceiptPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny memerlukan permission view_goods_receipt', function () {
    $policy = new GoodsReceiptPolicy;

    expect($policy->viewAny(makeTestUser(['view_goods_receipt'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('create dan finalize digerbang perform_goods_receipt — tanpa alur ambang', function () {
    $policy = new GoodsReceiptPolicy;
    $draft = GoodsReceipt::factory()->create();

    expect($policy->create(makeTestUser(['perform_goods_receipt'])))->toBeTrue()
        ->and($policy->create(makeTestUser(['view_goods_receipt'])))->toBeFalse()
        ->and($policy->finalize(makeTestUser(['perform_goods_receipt']), $draft))->toBeTrue()
        ->and($policy->finalize(makeTestUser(['review_goods_receipt']), $draft))->toBeFalse();
});

it('void memerlukan review_goods_receipt (Admin/Owner), BUKAN perform_goods_receipt (Gudang)', function () {
    $policy = new GoodsReceiptPolicy;
    $final = GoodsReceipt::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);
    $draft = GoodsReceipt::factory()->create();

    expect($policy->void(makeTestUser(['review_goods_receipt']), $final))->toBeTrue()
        ->and($policy->void(makeTestUser(['perform_goods_receipt']), $final))->toBeFalse()
        ->and($policy->void(makeTestUser(['review_goods_receipt']), $draft))->toBeFalse();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new GoodsReceiptPolicy;
    $draft = GoodsReceipt::factory()->create();

    expect($policy->forceDelete(makeTestUser(['review_goods_receipt']), $draft))->toBeFalse();
});
