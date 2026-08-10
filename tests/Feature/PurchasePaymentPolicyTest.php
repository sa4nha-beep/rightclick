<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\PurchasePayment;
use App\Infrastructure\Persistence\Policies\PurchasePaymentPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny memerlukan permission view_payables', function () {
    $policy = new PurchasePaymentPolicy;

    expect($policy->viewAny(makeTestUser(['view_payables'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('create digerbang record_cash_entry — BEDA dari SalePaymentPolicy yang selalu false', function () {
    $policy = new PurchasePaymentPolicy;

    expect($policy->create(makeTestUser(['record_cash_entry'])))->toBeTrue()
        ->and($policy->create(makeTestUser(['view_payables'])))->toBeFalse();
});

it('update/delete/restore selalu ditolak — pembayaran immutable begitu tercatat', function () {
    $policy = new PurchasePaymentPolicy;
    $payment = PurchasePayment::factory()->create();

    expect($policy->update(makeTestUser(['record_cash_entry']), $payment))->toBeFalse()
        ->and($policy->delete(makeTestUser(['record_cash_entry']), $payment))->toBeFalse()
        ->and($policy->restore(makeTestUser(['record_cash_entry']), $payment))->toBeFalse();
});

it('forceDelete selalu ditolak', function () {
    $policy = new PurchasePaymentPolicy;
    $payment = PurchasePayment::factory()->create();

    expect($policy->forceDelete(makeTestUser(['record_cash_entry']), $payment))->toBeFalse();
});
