<?php

declare(strict_types=1);

use App\Application\Actions\DispatchStockTransferAction;
use App\Application\Actions\ReceiveStockTransferAction;
use App\Application\Actions\VoidStockTransferAction;
use App\Application\Actions\VoidStockTransferReceiptAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->dispatchAction = app(DispatchStockTransferAction::class);
    $this->receiveAction = app(ReceiveStockTransferAction::class);
    $this->voidTransferAction = app(VoidStockTransferAction::class);
    $this->voidReceiptAction = app(VoidStockTransferReceiptAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->source = Branch::factory()->create();
    $this->dest = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['perform_transfer', 'void_stock_document']));

    DB::transaction(function () {
        $this->ledger->receive($this->source, $this->product, '10.0000', '15000.00', now(), Branch::factory()->create(), StockMutationType::Receipt);
    });
});

afterEach(function () {
    DB::rollBack();
});

it('void dispatch mengembalikan stok cabang asal bila belum diterima', function () {
    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);
    $transfer->lines()->create(['product_id' => $this->product->id, 'quantity' => '4.0000']);
    $dispatched = $this->dispatchAction->execute($transfer);

    expect($this->ledger->availableQuantity($this->source, $this->product))->toEqual('6.0000');

    $voided = $this->voidTransferAction->execute($dispatched, 'Batal kirim');

    expect($voided->state)->toBe(DocumentState::Void)
        ->and($this->ledger->availableQuantity($this->source, $this->product))->toEqual('10.0000');
});

it('void dispatch ditolak selama masih ada receipt aktif', function () {
    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);
    $transfer->lines()->create(['product_id' => $this->product->id, 'quantity' => '4.0000']);
    $dispatched = $this->dispatchAction->execute($transfer);
    $this->receiveAction->execute($dispatched);

    expect(fn () => $this->voidTransferAction->execute($dispatched->fresh(), 'Coba batal'))
        ->toThrow(StockDocumentValidationException::class);
});

it('void receipt lalu void dispatch mengembalikan stok sepenuhnya ke cabang asal', function () {
    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);
    $transfer->lines()->create(['product_id' => $this->product->id, 'quantity' => '4.0000']);
    $dispatched = $this->dispatchAction->execute($transfer);
    $receipt = $this->receiveAction->execute($dispatched);

    expect($this->ledger->availableQuantity($this->dest, $this->product))->toEqual('4.0000');

    $this->voidReceiptAction->execute($receipt, 'Salah kirim');
    expect($this->ledger->availableQuantity($this->dest, $this->product))->toEqual('0.0000');

    $voidedTransfer = $this->voidTransferAction->execute($dispatched->fresh(), 'Batal transfer');

    expect($voidedTransfer->state)->toBe(DocumentState::Void)
        ->and($this->ledger->availableQuantity($this->source, $this->product))->toEqual('10.0000');
});
