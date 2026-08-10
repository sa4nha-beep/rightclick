<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Presentation\Filament\Resources\Partners\Pages\CreatePartner;
use App\Presentation\Filament\Resources\Partners\PartnerResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar mitra dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_partners']));

    $this->get(PartnerResource::getUrl('index'))->assertOk();
});

it('halaman daftar mitra ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(PartnerResource::getUrl('index'))->assertForbidden();
});

it('form Buat Mitra menyimpan data lewat Livewire', function () {
    $this->actingAs(makeTestUser(['create_partners', 'view_partners']));

    Livewire::test(CreatePartner::class)
        ->fillForm([
            'code' => 'PTR-TST',
            'name' => 'Mitra Uji Livewire',
            'partner_type' => 'customer',
            'phone' => '0812-0000-0000',
            'email' => 'mitra@uji.test',
            'credit_limit' => 5_000_000,
            'payment_terms_days' => 14,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('partners', [
        'code' => 'PTR-TST',
        'name' => 'Mitra Uji Livewire',
        'partner_type' => 'customer',
    ]);
});
