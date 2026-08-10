<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\StockBalance;
use App\Presentation\Filament\Resources\StockBalances\StockBalanceResource;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman status stok dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_stock']));

    $this->get(StockBalanceResource::getUrl('index'))->assertOk();
});

it('halaman status stok ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(StockBalanceResource::getUrl('index'))->assertForbidden();
});

it('halaman detail status stok dapat diakses pengguna berwenang', function () {
    $user = makeTestUser(['view_stock']);
    $this->actingAs($user);
    // BranchScope (R12) menyaring record ke cabang aktif pengguna — batch
    // harus dibuat di cabang yang sama, kalau tidak halaman detail 404.
    $balance = StockBalance::factory()->create(['branch_id' => $user->default_branch_id]);

    $this->get(StockBalanceResource::getUrl('view', ['record' => $balance]))->assertOk();
});
