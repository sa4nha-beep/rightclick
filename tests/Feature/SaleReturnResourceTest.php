<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Filament\Resources\SaleReturns\Pages\CreateSaleReturn;
use App\Presentation\Filament\Resources\SaleReturns\SaleReturnResource;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('halaman daftar retur penjualan dapat diakses pengguna berwenang', function () {
    $this->actingAs(makeTestUser(['view_sale_returns']));

    $this->get(SaleReturnResource::getUrl('index'))->assertOk();
});

it('halaman daftar retur penjualan ditolak bagi pengguna tanpa permission', function () {
    $this->actingAs(makeTestUser());

    $this->get(SaleReturnResource::getUrl('index'))->assertForbidden();
});

it('form Buat Retur menyimpan header dan baris lewat Livewire', function () {
    $user = makeTestUser(['create_sale', 'create_sale_return', 'view_sale_returns']);
    $this->actingAs($user);
    app(BranchContext::class)->set($user->default_branch_id);

    $branch = Branch::find($user->default_branch_id);
    $product = Product::factory()->create();
    $shift = CashierShift::factory()->create(['branch_id' => $user->default_branch_id, 'cashier_id' => $user->id]);

    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $branch, $product, '10.0000', '8000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $sale = Sale::factory()->create(['branch_id' => $user->default_branch_id, 'cashier_shift_id' => $shift->id]);
    $item = $sale->items()->create(['product_id' => $product->id, 'quantity' => '2.0000', 'unit_price' => '15000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '30000.00']);
    $finalized = app(FinalizeSaleAction::class)->execute($sale);

    Livewire::test(CreateSaleReturn::class)
        ->fillForm([
            'sale_id' => $finalized->id,
            'lines' => [
                [
                    'sale_item_id' => $item->id,
                    'quantity' => 1,
                    'reason' => 'Barang cacat',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('sale_returns', [
        'sale_id' => $finalized->id,
        'state' => 'draft',
    ]);
    $this->assertDatabaseHas('sale_return_lines', [
        'sale_item_id' => $item->id,
        'quantity' => 1,
    ]);
});
