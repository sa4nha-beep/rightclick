<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Presentation\Filament\Resources\Units\Pages\CreateUnit;
use App\Presentation\Filament\Resources\Units\UnitResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar satuan dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_products']));

    $this->get(UnitResource::getUrl('index'))->assertOk();
});

it('halaman daftar satuan ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(UnitResource::getUrl('index'))->assertForbidden();
});

it('form Buat Satuan menyimpan data lewat Livewire', function () {
    $this->actingAs(makeTestUser(['create_products', 'view_products']));

    Livewire::test(CreateUnit::class)
        ->fillForm([
            'code' => 'TST',
            'name' => 'Satuan Uji',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('units', [
        'code' => 'TST',
        'name' => 'Satuan Uji',
    ]);
});
