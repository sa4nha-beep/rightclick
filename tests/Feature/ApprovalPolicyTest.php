<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\ApprovalStatus;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Policies\ApprovalPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny true bila punya request_approval atau decide_approval', function () {
    $policy = new ApprovalPolicy;

    expect($policy->viewAny(makeTestUser(['request_approval'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser(['decide_approval'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view mengizinkan pemohon melihat permintaannya sendiri (AP-02)', function () {
    $requester = makeTestUser();
    $approval = Approval::create([
        'branch_id' => $requester->default_branch_id,
        'approvable_type' => 'test_type',
        'approvable_id' => (string) Str::uuid7(),
        'requested_by' => $requester->id,
        'status' => ApprovalStatus::Pending,
        'requested_at' => now(),
    ]);

    $policy = new ApprovalPolicy;

    expect($policy->view($requester, $approval))->toBeTrue()
        ->and($policy->view(makeTestUser(), $approval))->toBeFalse()
        ->and($policy->view(makeTestUser(['decide_approval']), $approval))->toBeTrue();
});

it('decide memerlukan permission decide_approval', function () {
    $requester = makeTestUser();
    $approval = Approval::create([
        'branch_id' => $requester->default_branch_id,
        'approvable_type' => 'test_type',
        'approvable_id' => (string) Str::uuid7(),
        'requested_by' => $requester->id,
        'status' => ApprovalStatus::Pending,
        'requested_at' => now(),
    ]);

    $policy = new ApprovalPolicy;

    expect($policy->decide(makeTestUser(['decide_approval']), $approval))->toBeTrue()
        ->and($policy->decide(makeTestUser(), $approval))->toBeFalse();
});

it('update dan delete selalu ditolak — keputusan hanya berubah lewat decide()', function () {
    $requester = makeTestUser();
    $approval = Approval::create([
        'branch_id' => $requester->default_branch_id,
        'approvable_type' => 'test_type',
        'approvable_id' => (string) Str::uuid7(),
        'requested_by' => $requester->id,
        'status' => ApprovalStatus::Pending,
        'requested_at' => now(),
    ]);

    $policy = new ApprovalPolicy;
    $owner = makeTestUser(['decide_approval', 'manage_settings']);

    expect($policy->update($owner, $approval))->toBeFalse()
        ->and($policy->delete($owner, $approval))->toBeFalse();
});
