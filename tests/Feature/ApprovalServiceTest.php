<?php

declare(strict_types=1);

use App\Application\Services\ApprovalService;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Support\BranchContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * T1.9 AC — PRD §12.2 AP-01–AP-04. `Branch` dipakai sebagai `$approvable`
 * perancah murni: ApprovalService generik atas model apa pun, hanya
 * menyentuh `getMorphClass()`/`getKey()`, tidak ada kolom Branch yang
 * relevan secara bisnis di sini.
 */
beforeEach(function () {
    DB::beginTransaction();

    $this->service = new ApprovalService;
    $this->requester = makeTestUser();
    $this->approver = makeTestUser();

    app(BranchContext::class)->set($this->requester->default_branch_id);
});

afterEach(function () {
    DB::rollBack();
    app(BranchContext::class)->clear();
});

it('request membuat approval berstatus pending', function () {
    $branch = makeTestBranch();

    $approval = $this->service->request($branch, $this->requester->id);

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->approvable_type)->toBe($branch->getMorphClass())
        ->and($approval->approvable_id)->toBe($branch->getKey())
        ->and($approval->requested_by)->toBe($this->requester->id)
        ->and($approval->requested_at)->not->toBeNull()
        ->and($approval->approver_id)->toBeNull()
        ->and($approval->decided_at)->toBeNull();
});

it('approvable relation me-resolve kembali ke model asal', function () {
    $branch = makeTestBranch();

    $approval = $this->service->request($branch, $this->requester->id);

    expect($approval->approvable->is($branch))->toBeTrue();
});

it('approve menaikkan status menjadi approved dan mengisi decided_at', function () {
    $branch = makeTestBranch();
    $approval = $this->service->request($branch, $this->requester->id);

    $this->service->approve($approval, $this->approver->id);

    $fresh = $approval->fresh();
    expect($fresh->status)->toBe(ApprovalStatus::Approved)
        ->and($fresh->approver_id)->toBe($this->approver->id)
        ->and($fresh->decided_at)->not->toBeNull();
});

it('approve menolak permintaan yang sudah diputuskan', function () {
    $branch = makeTestBranch();
    $approval = $this->service->request($branch, $this->requester->id);
    $this->service->approve($approval, $this->approver->id);

    expect(fn () => $this->service->approve($approval, $this->approver->id))
        ->toThrow(ApprovalException::class);
});

it('AP-04 — reject tanpa alasan ditolak', function () {
    $branch = makeTestBranch();
    $approval = $this->service->request($branch, $this->requester->id);

    expect(fn () => $this->service->reject($approval, '', $this->approver->id))
        ->toThrow(ApprovalException::class);

    expect(fn () => $this->service->reject($approval, '   ', $this->approver->id))
        ->toThrow(ApprovalException::class);

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Pending);
});

it('reject dengan alasan mengisi seluruh field keputusan', function () {
    $branch = makeTestBranch();
    $approval = $this->service->request($branch, $this->requester->id);

    $this->service->reject($approval, 'Melebihi ambang tanpa justifikasi', $this->approver->id);

    $fresh = $approval->fresh();
    expect($fresh->status)->toBe(ApprovalStatus::Rejected)
        ->and($fresh->approver_id)->toBe($this->approver->id)
        ->and($fresh->decided_at)->not->toBeNull()
        ->and($fresh->reason)->toBe('Melebihi ambang tanpa justifikasi');
});

it('reject menolak permintaan yang sudah diputuskan', function () {
    $branch = makeTestBranch();
    $approval = $this->service->request($branch, $this->requester->id);
    $this->service->reject($approval, 'Alasan pertama', $this->approver->id);

    expect(fn () => $this->service->reject($approval, 'Alasan kedua', $this->approver->id))
        ->toThrow(ApprovalException::class);
});

it('C7-serupa — database menolak status rejected tanpa reason terisi', function () {
    $branch = makeTestBranch();
    $approval = $this->service->request($branch, $this->requester->id);

    // Melewati service untuk membuktikan constraint pada database itu
    // sendiri (AP-04), bukan hanya penjagaan di lapisan aplikasi.
    expect(fn () => DB::table('approvals')
        ->where('id', $approval->id)
        ->update(['status' => ApprovalStatus::Rejected->value]))
        ->toThrow(QueryException::class);
});
