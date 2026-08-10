<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Presentation\Filament\Resources\Branches\BranchResource;
use App\Presentation\Filament\Resources\Branches\Pages\CreateBranch;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar cabang dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_branches']));

    $this->get(BranchResource::getUrl('index'))->assertOk();
});

it('halaman daftar cabang ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(BranchResource::getUrl('index'))->assertForbidden();
});

it('form Buat Cabang menyimpan data lewat Livewire', function () {
    $this->actingAs(makeTestUser(['create_branches', 'view_branches']));

    Livewire::test(CreateBranch::class)
        ->fillForm([
            'code' => 'TST-01',
            'name' => 'Cabang Uji Livewire',
            'address' => 'Jl. Uji No. 1',
            'pic_name' => 'PIC Uji',
            'is_hq' => false,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('branches', [
        'code' => 'TST-01',
        'name' => 'Cabang Uji Livewire',
    ]);
});
