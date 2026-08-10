<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Presentation\Filament\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar penerimaan barang dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_goods_receipt']));

    $this->get(GoodsReceiptResource::getUrl('index'))->assertOk();
});

it('halaman daftar penerimaan barang ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(GoodsReceiptResource::getUrl('index'))->assertForbidden();
});

it('form Buat Penerimaan Barang menyimpan header dan baris lewat Livewire', function () {
    $user = makeTestUser(['perform_goods_receipt', 'view_goods_receipt']);
    $this->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);
    $supplier = Partner::factory()->create();
    $product = Product::factory()->create();

    Livewire::test(CreateGoodsReceipt::class)
        ->fillForm([
            'partner_id' => $supplier->id,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_cost' => 15000,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('goods_receipts', ['partner_id' => $supplier->id, 'state' => 'draft']);
    $this->assertDatabaseHas('goods_receipt_lines', ['product_id' => $product->id]);
});
