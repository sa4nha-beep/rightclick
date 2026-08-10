<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Presentation\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar purchase order dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_purchase_orders']));

    $this->get(PurchaseOrderResource::getUrl('index'))->assertOk();
});

it('halaman daftar purchase order ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(PurchaseOrderResource::getUrl('index'))->assertForbidden();
});

it('form Buat Purchase Order menyimpan header dan baris lewat Livewire', function () {
    $user = makeTestUser(['create_purchase_order', 'view_purchase_orders']);
    $this->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);
    $supplier = Partner::factory()->create();
    $product = Product::factory()->create();

    Livewire::test(CreatePurchaseOrder::class)
        ->fillForm([
            'partner_id' => $supplier->id,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 12000,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('purchase_orders', ['partner_id' => $supplier->id, 'state' => 'draft']);
    $this->assertDatabaseHas('purchase_order_lines', ['product_id' => $product->id]);
});
