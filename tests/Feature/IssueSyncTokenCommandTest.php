<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Branch;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('menerbitkan token baru dan menampilkannya sekali, menyimpan hanya hash-nya', function () {
    $branch = Branch::factory()->create(['code' => 'HK-TEST']);

    $this->artisan('sync:issue-token', ['branch_code' => 'HK-TEST'])
        ->assertSuccessful();

    $branch->refresh();
    expect($branch->sync_token_hash)->not->toBeNull()
        ->and(strlen($branch->sync_token_hash))->toBe(64);
});

it('menerbitkan token baru membuat token lama tidak berlaku', function () {
    $branch = Branch::factory()->create(['code' => 'HK-TEST']);
    $firstToken = $branch->issueSyncToken();

    $this->artisan('sync:issue-token', ['branch_code' => 'HK-TEST'])->assertSuccessful();

    expect($branch->fresh()->verifySyncToken($firstToken))->toBeFalse();
});

it('gagal untuk kode cabang yang tidak ditemukan', function () {
    $this->artisan('sync:issue-token', ['branch_code' => 'TIDAK-ADA'])
        ->assertFailed();
});
