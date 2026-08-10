<?php

declare(strict_types=1);

use App\Application\Actions\DispatchStockTransferAction;
use App\Application\Actions\ReceiveStockTransferAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->dispatchAction = app(DispatchStockTransferAction::class);
    $this->receiveAction = app(ReceiveStockTransferAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->source = Branch::factory()->create();
    $this->dest = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['perform_transfer']));

    DB::transaction(function () {
        $this->ledger->receive($this->source, $this->product, '10.0000', '15000.00', now(), Branch::factory()->create(), StockMutationType::Receipt);
    });
});

afterEach(function () {
    DB::rollBack();
});

it('menolak menerima dokumen yang belum dikirim', function () {
    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);

    expect(fn () => $this->receiveAction->execute($transfer))->toThrow(StockDocumentValidationException::class);
});

it('AC-11 — barang transit tidak tersedia di cabang mana pun antara kirim dan terima', function () {
    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);
    $transfer->lines()->create(['product_id' => $this->product->id, 'quantity' => '6.0000']);

    $dispatched = $this->dispatchAction->execute($transfer);

    expect($dispatched->state)->toBe(DocumentState::Final)
        ->and($this->ledger->availableQuantity($this->source, $this->product))->toEqual('4.0000')
        ->and($this->ledger->availableQuantity($this->dest, $this->product))->toEqual('0.0000');

    $receipt = $this->receiveAction->execute($dispatched);

    expect($receipt->state)->toBe(DocumentState::Final)
        ->and($receipt->document_number)->toContain('TRI')
        ->and($this->ledger->availableQuantity($this->source, $this->product))->toEqual('4.0000')
        ->and($this->ledger->availableQuantity($this->dest, $this->product))->toEqual('6.0000');
});

it('mewarisi rincian biaya per batch sumber ke batch baru cabang tujuan', function () {
    DB::transaction(function () {
        $this->ledger->receive($this->source, $this->product, '5.0000', '30000.00', now(), Branch::factory()->create(), StockMutationType::Receipt);
    });

    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);
    $transfer->lines()->create(['product_id' => $this->product->id, 'quantity' => '12.0000']);

    $dispatched = $this->dispatchAction->execute($transfer);
    $this->receiveAction->execute($dispatched);

    $destBatches = StockBatch::query()
        ->where('branch_id', $this->dest->id)
        ->where('product_id', $this->product->id)
        ->orderBy('unit_cost')
        ->get();

    expect($destBatches)->toHaveCount(2)
        ->and((string) $destBatches[0]->unit_cost)->toEqual('15000.00')
        ->and((string) $destBatches[0]->qty_received)->toEqual('10.0000')
        ->and((string) $destBatches[1]->unit_cost)->toEqual('30000.00')
        ->and((string) $destBatches[1]->qty_received)->toEqual('2.0000');
});

it('menolak menerima dua kali untuk dokumen yang sama', function () {
    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);
    $transfer->lines()->create(['product_id' => $this->product->id, 'quantity' => '3.0000']);

    $dispatched = $this->dispatchAction->execute($transfer);
    $this->receiveAction->execute($dispatched);

    expect(fn () => $this->receiveAction->execute($dispatched->fresh()))
        ->toThrow(StockDocumentValidationException::class);
});
