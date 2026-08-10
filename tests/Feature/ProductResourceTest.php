<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Infrastructure\Persistence\Models\Unit;
use App\Presentation\Filament\Resources\Products\Pages\CreateProduct;
use App\Presentation\Filament\Resources\Products\ProductResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar produk dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_products']));

    $this->get(ProductResource::getUrl('index'))->assertOk();
});

it('halaman daftar produk ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(ProductResource::getUrl('index'))->assertForbidden();
});

it('form Buat Produk menyimpan data lewat Livewire, termasuk kategori dan satuan', function () {
    $this->actingAs(makeTestUser(['create_products', 'view_products']));
    $category = ProductCategory::factory()->create();
    $unit = Unit::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'sku' => 'SKU-TST-001',
            'name' => 'Produk Uji Livewire',
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'selling_price' => 199_000,
            'is_active' => true,
            'is_serialized' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('products', [
        'sku' => 'SKU-TST-001',
        'product_category_id' => $category->id,
        'base_unit_id' => $unit->id,
    ]);
});

it('form Buat Produk menolak harga jual nol lewat validasi', function () {
    $this->actingAs(makeTestUser(['create_products', 'view_products']));
    $category = ProductCategory::factory()->create();
    $unit = Unit::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'sku' => 'SKU-TST-002',
            'name' => 'Produk Harga Nol',
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'selling_price' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['selling_price']);
});
