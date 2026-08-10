<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\ReceivablePayment;
use App\Infrastructure\Persistence\Policies\ReceivablePaymentPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny memerlukan permission view_receivables', function () {
    $policy = new ReceivablePaymentPolicy;

    expect($policy->viewAny(makeTestUser(['view_receivables'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('create digerbang record_cash_entry — BEDA dari SalePaymentPolicy yang selalu false', function () {
    $policy = new ReceivablePaymentPolicy;

    expect($policy->create(makeTestUser(['record_cash_entry'])))->toBeTrue()
        ->and($policy->create(makeTestUser(['view_receivables'])))->toBeFalse();
});

it('update/delete/restore selalu ditolak — pelunasan immutable begitu tercatat', function () {
    $policy = new ReceivablePaymentPolicy;
    $payment = ReceivablePayment::factory()->create();

    expect($policy->update(makeTestUser(['record_cash_entry']), $payment))->toBeFalse()
        ->and($policy->delete(makeTestUser(['record_cash_entry']), $payment))->toBeFalse()
        ->and($policy->restore(makeTestUser(['record_cash_entry']), $payment))->toBeFalse();
});

it('forceDelete selalu ditolak', function () {
    $policy = new ReceivablePaymentPolicy;
    $payment = ReceivablePayment::factory()->create();

    expect($policy->forceDelete(makeTestUser(['record_cash_entry']), $payment))->toBeFalse();
});
