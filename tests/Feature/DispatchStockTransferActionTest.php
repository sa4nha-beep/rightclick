<?php

declare(strict_types=1);

use App\Application\Actions\DispatchStockTransferAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(DispatchStockTransferAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->source = Branch::factory()->create();
    $this->dest = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->actingAs(makeTestUser(['perform_transfer']));
});

afterEach(function () {
    DB::rollBack();
});

it('menolak dokumen tanpa baris', function () {
    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);

    expect(fn () => $this->action->execute($transfer))->toThrow(StockDocumentValidationException::class);
});

it('mengonsumsi FIFO dari cabang asal dan merekam rincian batch sumber', function () {
    DB::transaction(function () {
        $ref = Branch::factory()->create();
        $this->ledger->receive($this->source, $this->product, '5.0000', '10000.00', now()->subDays(5), $ref, StockMutationType::Receipt);
        $this->ledger->receive($this->source, $this->product, '5.0000', '20000.00', now()->subDay(), $ref, StockMutationType::Receipt);
    });

    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);
    $transfer->lines()->create(['product_id' => $this->product->id, 'quantity' => '7.0000']);

    $result = $this->action->execute($transfer);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->document_number)->toContain('TRO');

    $lineBatches = $result->lines->first()->lineBatches;
    expect($lineBatches)->toHaveCount(2)
        ->and((string) $lineBatches[0]->quantity)->toEqual('5.0000')
        ->and((string) $lineBatches[0]->unit_cost)->toEqual('10000.00')
        ->and((string) $lineBatches[1]->quantity)->toEqual('2.0000')
        ->and((string) $lineBatches[1]->unit_cost)->toEqual('20000.00');

    expect($this->ledger->availableQuantity($this->source, $this->product))->toEqual('3.0000');
});

it('menolak bila stok cabang asal tidak mencukupi — R7', function () {
    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);
    $transfer->lines()->create(['product_id' => $this->product->id, 'quantity' => '5.0000']);

    expect(fn () => $this->action->execute($transfer))->toThrow(InsufficientStockException::class);
});

it('T3.7 — setiap baris transfer produk serial wajib mengisi serial number sejumlah kuantitas', function () {
    $serialized = Product::factory()->create(['is_serialized' => true]);

    DB::transaction(function () use ($serialized) {
        $this->ledger->receive($this->source, $serialized, '2.0000', '1000000.00', now(), Branch::factory()->create(), StockMutationType::Receipt);
    });

    $transfer = StockTransfer::factory()->create(['branch_id' => $this->source->id, 'dest_branch_id' => $this->dest->id]);
    $transfer->lines()->create(['product_id' => $serialized->id, 'quantity' => '2.0000', 'serial_numbers' => ['SN-1']]);

    expect(fn () => $this->action->execute($transfer))
        ->toThrow(StockDocumentValidationException::class);

    $transfer->lines()->first()->update(['serial_numbers' => ['SN-1', 'SN-2']]);

    $result = $this->action->execute($transfer->fresh());
    expect($result->state)->toBe(DocumentState::Final);
});
