<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Actions\FinalizeSaleReturnAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\SaleReturn;
use App\Infrastructure\Persistence\Models\Service;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->finalizeSaleAction = app(FinalizeSaleAction::class);
    $this->action = app(FinalizeSaleReturnAction::class);
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

function makeFinalizedSaleWithProductLine(FinalizeSaleAction $action, Branch $branch, CashierShift $shift, Product $product, string $qty, string $unitPrice): Sale
{
    $sale = Sale::factory()->create(['branch_id' => $branch->id, 'cashier_shift_id' => $shift->id]);
    $sale->items()->create(['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $unitPrice]);
    $sale->payments()->create(['method' => 'cash', 'amount' => bcmul($qty, $unitPrice, 2)]);

    return $action->execute($sale);
}

it('menolak retur tanpa baris', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '10000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));
    $sale = makeFinalizedSaleWithProductLine($this->finalizeSaleAction, $this->branch, $this->shift, $this->product, '2.0000', '15000.00');

    $return = SaleReturn::factory()->create(['branch_id' => $this->branch->id, 'sale_id' => $sale->id]);

    expect(fn () => $this->action->execute($return))->toThrow(SaleValidationException::class);
});

it('menolak retur atas penjualan yang belum final', function () {
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);
    $item = $sale->items()->create(['product_id' => $this->product->id, 'quantity' => '2.0000', 'unit_price' => '15000.00']);

    $return = SaleReturn::factory()->create(['branch_id' => $this->branch->id, 'sale_id' => $sale->id]);
    $return->lines()->create(['sale_item_id' => $item->id, 'quantity' => '1.0000', 'reason' => 'Rusak']);

    expect(fn () => $this->action->execute($return))->toThrow(SaleValidationException::class);
});

it('AC-18 — retur kembali pada nilai perolehan batch asal (HPP), bukan harga jual', function () {
    // Batch dibeli Rp8.000, dijual Rp15.000 (margin). Retur harus masuk stok pada Rp8.000, bukan Rp15.000.
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '8000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));
    $sale = makeFinalizedSaleWithProductLine($this->finalizeSaleAction, $this->branch, $this->shift, $this->product, '3.0000', '15000.00');
    $item = $sale->items->first();

    expect((string) $item->unit_cost_snapshot)->toEqual('8000.00');

    $return = SaleReturn::factory()->create(['branch_id' => $this->branch->id, 'sale_id' => $sale->id]);
    $return->lines()->create(['sale_item_id' => $item->id, 'quantity' => '2.0000', 'reason' => 'Barang cacat']);

    $result = $this->action->execute($return);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->document_number)->toContain('RET');

    $line = $result->lines->first();
    expect((string) $line->unit_cost)->toEqual('8000.00')
        ->and((string) $line->unit_price)->toEqual('15000.00')
        ->and((string) $line->refund_amount)->toEqual('30000.00')
        ->and((string) $result->total_refund)->toEqual('30000.00');

    // Stok kembali: 10 dibeli - 3 terjual + 2 diretur = 9.
    expect(app(StockLedgerService::class)->availableQuantity($this->branch, $this->product))->toEqual('9.0000');
});

it('menolak retur baris jasa', function () {
    $service = Service::factory()->create(['price' => '50000.00']);
    $sale = Sale::factory()->create(['branch_id' => $this->branch->id, 'cashier_shift_id' => $this->shift->id]);
    $item = $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '50000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '50000.00']);
    $finalized = $this->finalizeSaleAction->execute($sale);

    $return = SaleReturn::factory()->create(['branch_id' => $this->branch->id, 'sale_id' => $finalized->id]);
    $return->lines()->create(['sale_item_id' => $item->id, 'quantity' => '1.0000', 'reason' => 'Batal']);

    expect(fn () => $this->action->execute($return))->toThrow(SaleValidationException::class);
});

it('menolak retur melebihi sisa yang belum diretur — akumulasi lintas dokumen', function () {
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $this->product, '10.0000', '8000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));
    $sale = makeFinalizedSaleWithProductLine($this->finalizeSaleAction, $this->branch, $this->shift, $this->product, '5.0000', '15000.00');
    $item = $sale->items->first();

    $firstReturn = SaleReturn::factory()->create(['branch_id' => $this->branch->id, 'sale_id' => $sale->id]);
    $firstReturn->lines()->create(['sale_item_id' => $item->id, 'quantity' => '3.0000', 'reason' => 'Cacat sebagian']);
    $this->action->execute($firstReturn);

    // Sisa hanya 2 (5 - 3), retur kedua minta 3 → ditolak.
    $secondReturn = SaleReturn::factory()->create(['branch_id' => $this->branch->id, 'sale_id' => $sale->id]);
    $secondReturn->lines()->create(['sale_item_id' => $item->id, 'quantity' => '3.0000', 'reason' => 'Cacat lagi']);

    expect(fn () => $this->action->execute($secondReturn))->toThrow(SaleValidationException::class);
});

it('T3.7 — produk serial wajib mengisi serial number sejumlah kuantitas retur', function () {
    $serialized = Product::factory()->create(['is_serialized' => true]);
    DB::transaction(fn () => app(StockLedgerService::class)->receive(
        $this->branch, $serialized, '5.0000', '500000.00', now(), Branch::factory()->create(), StockMutationType::Receipt,
    ));
    $sale = makeFinalizedSaleWithProductLine($this->finalizeSaleAction, $this->branch, $this->shift, $serialized, '2.0000', '750000.00');
    $item = $sale->items->first();

    $return = SaleReturn::factory()->create(['branch_id' => $this->branch->id, 'sale_id' => $sale->id]);
    $return->lines()->create([
        'sale_item_id' => $item->id,
        'quantity' => '2.0000',
        'reason' => 'Cacat',
        'serial_numbers' => ['SN-A'],
    ]);

    expect(fn () => $this->action->execute($return))->toThrow(StockDocumentValidationException::class);

    $return->lines()->first()->update(['serial_numbers' => ['SN-A', 'SN-B']]);

    $result = $this->action->execute($return->fresh());
    expect($result->state)->toBe(DocumentState::Final);
});
