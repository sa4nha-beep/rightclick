<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Presentation\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Presentation\Filament\Resources\ProductCategories\ProductCategoryResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar kategori produk dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_products']));

    $this->get(ProductCategoryResource::getUrl('index'))->assertOk();
});

it('halaman daftar kategori produk ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(ProductCategoryResource::getUrl('index'))->assertForbidden();
});

it('form Buat Kategori menyimpan data lewat Livewire, termasuk memilih induk', function () {
    $this->actingAs(makeTestUser(['create_products', 'view_products']));
    $parent = ProductCategory::factory()->create(['code' => 'PARENT-TST']);

    Livewire::test(CreateProductCategory::class)
        ->fillForm([
            'code' => 'CHILD-TST',
            'name' => 'Subkategori Uji',
            'parent_id' => $parent->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('product_categories', [
        'code' => 'CHILD-TST',
        'parent_id' => $parent->id,
    ]);
});
