<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeGoodsReceiptAction;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Presentation\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar faktur pembelian dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_goods_receipt']));

    $this->get(PurchaseInvoiceResource::getUrl('index'))->assertOk();
});

it('halaman daftar faktur pembelian ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(PurchaseInvoiceResource::getUrl('index'))->assertForbidden();
});

it('form Buat Faktur Pembelian menyimpan header lewat Livewire, hanya menawarkan penerimaan final tanpa faktur', function () {
    $user = makeTestUser(['approve_goods_receipt', 'perform_goods_receipt', 'view_goods_receipt']);
    $this->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);
    $supplier = Partner::factory()->create();
    $product = Product::factory()->create();

    $gr = GoodsReceipt::factory()->create(['branch_id' => $user->default_branch_id, 'partner_id' => $supplier->id]);
    $gr->lines()->create(['product_id' => $product->id, 'quantity' => '5.0000', 'unit_cost' => '10000.00']);
    $finalizedGr = app(FinalizeGoodsReceiptAction::class)->execute($gr);

    Livewire::test(CreatePurchaseInvoice::class)
        ->fillForm([
            'goods_receipt_id' => $finalizedGr->id,
            'partner_id' => $supplier->id,
            'invoice_number' => 'INV-EXT-001',
            'invoice_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('purchase_invoices', [
        'goods_receipt_id' => $finalizedGr->id,
        'invoice_number' => 'INV-EXT-001',
        'state' => 'draft',
    ]);
});
