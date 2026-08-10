<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Actions\FinalizeSaleReturnAction;
use App\Application\Actions\VoidSaleReturnAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\SaleReturn;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeSaleAction = app(FinalizeSaleAction::class);
    $this->finalizeReturnAction = app(FinalizeSaleReturnAction::class);
    $this->voidAction = app(VoidSaleReturnAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->user = makeTestUser(['create_sale', 'create_sale_return', 'process_sale_return']);
    $this->actingAs($this->user);
    $this->shift = CashierShift::factory()->create([
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->user->id,
    ]);
});

afterEach(function () {
    DB::rollBack();
});

it('void menarik kembali stok yang tadi masuk lewat retur', function () {
    DB::transaction(fn () => $this->ledger->receive(
        $this->branch, $this->product, '10.0000', '8000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);
    $item = $sale->items()->create(['product_id' => $this->product->id, 'quantity' => '4.0000', 'unit_price' => '15000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '60000.00']);
    $finalizedSale = $this->finalizeSaleAction->execute($sale);

    expect($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('6.0000');

    $return = SaleReturn::factory()->create(['branch_id' => $this->branch->id, 'sale_id' => $finalizedSale->id]);
    $return->lines()->create(['sale_item_id' => $item->id, 'quantity' => '2.0000', 'reason' => 'Cacat']);
    $finalizedReturn = $this->finalizeReturnAction->execute($return);

    expect($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('8.0000');

    $voided = $this->voidAction->execute($finalizedReturn, 'Salah input kasir');

    expect($voided->state)->toBe(DocumentState::Void)
        ->and($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('6.0000');
});
