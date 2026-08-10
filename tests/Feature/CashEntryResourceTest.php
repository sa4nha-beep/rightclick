<?php

declare(strict_types=1);

use App\Presentation\Filament\Resources\CashEntries\CashEntryResource;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman ledger kas dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_cash_entries']));

    $this->get(CashEntryResource::getUrl('index'))->assertOk();
});

it('halaman ledger kas ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(CashEntryResource::getUrl('index'))->assertForbidden();
});
