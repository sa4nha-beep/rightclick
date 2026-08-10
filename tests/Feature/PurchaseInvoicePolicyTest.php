<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use App\Infrastructure\Persistence\Policies\PurchaseInvoicePolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny memerlukan permission view_goods_receipt — payung baca bersama GoodsReceipt', function () {
    $policy = new PurchaseInvoicePolicy;

    expect($policy->viewAny(makeTestUser(['view_goods_receipt'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('seluruh aksi tulis digerbang approve_goods_receipt', function () {
    $policy = new PurchaseInvoicePolicy;
    $draft = PurchaseInvoice::factory()->create();
    $final = PurchaseInvoice::factory()->create(['state' => DocumentState::Final, 'finalized_at' => now()]);

    expect($policy->create(makeTestUser(['approve_goods_receipt'])))->toBeTrue()
        ->and($policy->create(makeTestUser(['view_goods_receipt'])))->toBeFalse()
        ->and($policy->update(makeTestUser(['approve_goods_receipt']), $draft))->toBeTrue()
        ->and($policy->update(makeTestUser(['approve_goods_receipt']), $final))->toBeFalse()
        ->and($policy->finalize(makeTestUser(['approve_goods_receipt']), $draft))->toBeTrue()
        ->and($policy->void(makeTestUser(['approve_goods_receipt']), $final))->toBeTrue()
        ->and($policy->void(makeTestUser(['approve_goods_receipt']), $draft))->toBeFalse();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new PurchaseInvoicePolicy;
    $draft = PurchaseInvoice::factory()->create();

    expect($policy->forceDelete(makeTestUser(['approve_goods_receipt']), $draft))->toBeFalse();
});
