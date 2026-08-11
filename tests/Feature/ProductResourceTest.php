<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Infrastructure\Persistence\Models\Unit;
use App\Presentation\Filament\Resources\Products\Pages\CreateProduct;
use App\Presentation\Filament\Resources\Products\Pages\EditProduct;
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

/**
 * Membuktikan `EditProduct::handleRecordUpdate()` (penutupan PT16) benar-
 * benar TERPASANG lewat halaman Filament sungguhan — bukan hanya
 * `ChangeProductSellingPriceAction` yang benar dipanggil langsung
 * (`ChangeProductSellingPriceActionTest.php`). Gotcha T5.6/T4.3 sebelumnya
 * membuktikan mekanisme yang benar di level Action/Policy tidak menjamin
 * kabelnya tersambung di jalur Livewire sungguhan.
 */
it('EditProduct menerapkan perubahan harga di bawah ambang langsung', function () {
    $this->actingAs(makeTestUser(['view_products', 'edit_products', 'manage_product_prices']));
    $product = Product::factory()->create(['selling_price' => '100000.00']);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['selling_price' => 105_000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((string) $product->fresh()->selling_price)->toEqual('105000.00')
        ->and(Approval::query()->count())->toBe(0);
});

it('EditProduct mengalihkan perubahan harga di atas ambang ke Approval, harga TIDAK berubah', function () {
    $this->actingAs(makeTestUser(['view_products', 'edit_products', 'manage_product_prices']));
    $product = Product::factory()->create(['selling_price' => '100000.00', 'name' => 'Nama Lama']);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['name' => 'Nama Baru', 'selling_price' => 130_000])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $product->fresh();

    // Field lain (nama) tetap diterapkan langsung meski harga tertunda.
    expect((string) $fresh->selling_price)->toEqual('100000.00')
        ->and($fresh->name)->toEqual('Nama Baru');

    $approval = Approval::query()
        ->where('approvable_type', $product->getMorphClass())
        ->where('approvable_id', $product->id)
        ->sole();

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->payload['proposed_selling_price'])->toEqual('130000');
});
