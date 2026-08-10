<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Filament\Resources\CashierShifts\CashierShiftResource;
use App\Presentation\Filament\Resources\CashierShifts\Pages\CreateCashierShift;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar shift kasir dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['close_cashier_shift']));

    $this->get(CashierShiftResource::getUrl('index'))->assertOk();
});

it('halaman daftar shift kasir ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(CashierShiftResource::getUrl('index'))->assertForbidden();
});

it('form Buka Shift menyimpan draft lewat Livewire', function () {
    $user = makeTestUser(['close_cashier_shift']);
    $this->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);

    Livewire::test(CreateCashierShift::class)
        ->fillForm(['opening_cash' => 500_000])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('cashier_shifts', [
        'cashier_id' => $user->id,
        'opening_cash' => 500_000,
        'state' => 'draft',
    ]);
});
