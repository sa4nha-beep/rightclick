<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeGoodsReceiptAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockBatch;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(FinalizeGoodsReceiptAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->supplier = Partner::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['perform_goods_receipt']));
});

afterEach(function () {
    DB::rollBack();
});

function makeGoodsReceiptWithLine(Branch $branch, Partner $partner, Product $product, string $qty, string $unitCost): GoodsReceipt
{
    $gr = GoodsReceipt::factory()->create(['branch_id' => $branch->id, 'partner_id' => $partner->id]);
    $gr->lines()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'unit_cost' => $unitCost,
    ]);

    return $gr;
}

it('menolak dokumen tanpa baris', function () {
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);

    expect(fn () => $this->action->execute($gr))
        ->toThrow(StockDocumentValidationException::class);
});

it('memanggil StockLedgerService::receive() — stok bertambah dan unit_cost termasuk PPN tersimpan apa adanya (AC-09)', function () {
    $gr = makeGoodsReceiptWithLine($this->branch, $this->supplier, $this->product, '10.0000', '115000.00');

    $result = $this->action->execute($gr);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->document_number)->toContain('PB')
        ->and((string) $result->total_amount)->toEqual('1150000.00')
        ->and($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('10.0000');

    $batch = StockBatch::query()
        ->where('product_id', $this->product->id)
        ->where('branch_id', $this->branch->id)
        ->sole();

    expect((string) $batch->unit_cost)->toEqual('115000.00');
});

it('total_amount dijumlahkan dari seluruh baris', function () {
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $gr->lines()->create(['product_id' => $this->product->id, 'quantity' => '2.0000', 'unit_cost' => '10000.00']);
    $secondProduct = Product::factory()->create();
    $gr->lines()->create(['product_id' => $secondProduct->id, 'quantity' => '3.0000', 'unit_cost' => '5000.00']);

    $result = $this->action->execute($gr->fresh());

    // (2 x 10.000) + (3 x 5.000) = 35.000
    expect((string) $result->total_amount)->toEqual('35000.00');
});

it('T3.7 — baris produk serial wajib mengisi serial number sejumlah kuantitas', function () {
    $serialized = Product::factory()->create(['is_serialized' => true]);
    $gr = GoodsReceipt::factory()->create(['branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id]);
    $gr->lines()->create([
        'product_id' => $serialized->id,
        'quantity' => '2.0000',
        'unit_cost' => '500000.00',
        'serial_numbers' => ['SN-X'],
    ]);

    expect(fn () => $this->action->execute($gr))
        ->toThrow(StockDocumentValidationException::class);

    $gr->lines()->first()->update(['serial_numbers' => ['SN-X', 'SN-Y']]);

    $result = $this->action->execute($gr->fresh());
    expect($result->state)->toBe(DocumentState::Final);
});
