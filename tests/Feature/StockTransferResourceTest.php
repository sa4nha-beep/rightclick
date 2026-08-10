<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Presentation\Filament\Resources\StockTransfers\StockTransferResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar transfer stok dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['perform_transfer']));

    $this->get(StockTransferResource::getUrl('index'))->assertOk();
});

it('halaman daftar transfer stok ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(StockTransferResource::getUrl('index'))->assertForbidden();
});

it('form Buat Transfer Stok menyimpan header dan baris lewat Livewire', function () {
    $user = makeTestUser(['perform_transfer']);
    $this->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);
    $destBranch = Branch::factory()->create();
    $product = Product::factory()->create();

    Livewire::test(CreateStockTransfer::class)
        ->fillForm([
            'dest_branch_id' => $destBranch->id,
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('stock_transfers', [
        'dest_branch_id' => $destBranch->id,
        'state' => 'draft',
    ]);
    $this->assertDatabaseHas('stock_transfer_lines', ['product_id' => $product->id]);
});
