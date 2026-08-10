<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Presentation\Filament\Resources\Services\Pages\CreateService;
use App\Presentation\Filament\Resources\Services\ServiceResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar jasa dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_products']));

    $this->get(ServiceResource::getUrl('index'))->assertOk();
});

it('halaman daftar jasa ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(ServiceResource::getUrl('index'))->assertForbidden();
});

it('form Buat Jasa menyimpan data lewat Livewire', function () {
    $this->actingAs(makeTestUser(['create_products', 'view_products']));

    Livewire::test(CreateService::class)
        ->fillForm([
            'code' => 'SVC-TST',
            'name' => 'Jasa Uji Livewire',
            'category' => 'Uji Coba',
            'price' => 100_000,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('services', [
        'code' => 'SVC-TST',
        'name' => 'Jasa Uji Livewire',
    ]);
});
