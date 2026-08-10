<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Presentation\Filament\Resources\Employees\EmployeeResource;
use App\Presentation\Filament\Resources\Employees\Pages\CreateEmployee;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar karyawan dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_users']));

    $this->get(EmployeeResource::getUrl('index'))->assertOk();
});

it('halaman daftar karyawan ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(EmployeeResource::getUrl('index'))->assertForbidden();
});

it('form Buat Karyawan menyimpan data lewat Livewire tanpa akun login', function () {
    $this->actingAs(makeTestUser(['create_users', 'view_users']));

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'name' => 'Karyawan Uji Livewire',
            'id_number' => '3319000000000099',
            'position' => 'Kasir',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('employees', [
        'name' => 'Karyawan Uji Livewire',
        'user_id' => null,
    ]);
});
