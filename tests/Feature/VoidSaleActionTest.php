<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Actions\VoidSaleAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeAction = app(FinalizeSaleAction::class);
    $this->voidAction = app(VoidSaleAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->user = makeTestUser(['create_sale', 'void_sale']);
    $this->actingAs($this->user);
    $this->shift = CashierShift::factory()->create([
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->user->id,
    ]);
});

afterEach(function () {
    DB::rollBack();
});

it('void mengembalikan stok yang terjual (mutasi berlawanan)', function () {
    DB::transaction(fn () => $this->ledger->receive(
        $this->branch, $this->product, '10.0000', '5000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));

    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);
    $sale->items()->create(['product_id' => $this->product->id, 'quantity' => '4.0000', 'unit_price' => '9000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '36000.00']);

    $finalized = $this->finalizeAction->execute($sale);
    expect($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('6.0000');

    $voided = $this->voidAction->execute($finalized, 'Salah input kasir');

    expect($voided->state)->toBe(DocumentState::Void)
        ->and($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('10.0000');
});
