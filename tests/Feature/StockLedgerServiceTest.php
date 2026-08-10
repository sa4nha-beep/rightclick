<?php

declare(strict_types=1);

use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockMutation;
use Illuminate\Support\Facades\DB;

/**
 * T3.2 — StockLedgerService, simpul kritis (R1/R2/R7).
 *
 * `$reference` di seluruh test ini adalah `Branch` sungguhan dipakai murni
 * sebagai "dokumen apa pun" (morph target) — belum ada model dokumen nyata
 * (StockOpname dkk. baru T3.4+). Mekanisme ledger tidak peduli jenis
 * dokumennya, hanya `getMorphClass()`/`getKey()`.
 */
beforeEach(function () {
    DB::beginTransaction();
    $this->service = app(StockLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
    $this->reference = Branch::factory()->create();
});

afterEach(function () {
    DB::rollBack();
});

it('receive menerbitkan batch baru dan menaikkan stock_balances', function () {
    DB::transaction(function () {
        $batch = $this->service->receive(
            $this->branch,
            $this->product,
            '10.0000',
            '50000.00',
            now(),
            $this->reference,
            StockMutationType::Receipt,
        );

        expect($batch->qty_received)->toEqual('10.0000')
            ->and($batch->qty_remaining)->toEqual('10.0000')
            ->and($batch->unit_cost)->toEqual('50000.00');

        expect($this->service->availableQuantity($this->branch, $this->product))->toEqual('10.0000');

        $mutation = StockMutation::query()->where('stock_batch_id', $batch->id)->sole();
        expect((string) $mutation->quantity)->toEqual('10.0000')
            ->and($mutation->mutation_type)->toBe(StockMutationType::Receipt)
            ->and($mutation->reference_type)->toBe($this->reference->getMorphClass())
            ->and($mutation->reference_id)->toBe($this->reference->id);
    });
});

it('consume mengambil batch tertua lebih dulu — FIFO (AC-08)', function () {
    DB::transaction(function () {
        $old = $this->service->receive($this->branch, $this->product, '5.0000', '10000.00', now()->subDays(10), $this->reference, StockMutationType::Receipt);
        $new = $this->service->receive($this->branch, $this->product, '5.0000', '20000.00', now()->subDays(1), $this->reference, StockMutationType::Receipt);

        $consumptions = $this->service->consume($this->branch, $this->product, '7.0000', $this->reference, StockMutationType::Sale);

        expect($consumptions)->toHaveCount(2);
        expect($consumptions[0]->stockBatchId)->toBe($old->id)
            ->and($consumptions[0]->quantity)->toEqual('5.0000')
            ->and($consumptions[0]->unitCost)->toEqual('10000.00');
        expect($consumptions[1]->stockBatchId)->toBe($new->id)
            ->and($consumptions[1]->quantity)->toEqual('2.0000')
            ->and($consumptions[1]->unitCost)->toEqual('20000.00');

        expect($old->fresh()->qty_remaining)->toEqual('0.0000')
            ->and($new->fresh()->qty_remaining)->toEqual('3.0000');

        expect($this->service->availableQuantity($this->branch, $this->product))->toEqual('3.0000');
    });
});

it('consume menolak permintaan melebihi stok tersedia — R7', function () {
    DB::transaction(function () {
        $this->service->receive($this->branch, $this->product, '3.0000', '10000.00', now(), $this->reference, StockMutationType::Receipt);

        expect(fn () => $this->service->consume($this->branch, $this->product, '5.0000', $this->reference, StockMutationType::Sale))
            ->toThrow(InsufficientStockException::class);

        // Stok tidak pernah negatif — batch tidak ikut berubah setelah rollback percobaan gagal.
        expect($this->service->availableQuantity($this->branch, $this->product))->toEqual('3.0000');
    });
});

it('consume tanpa batch sama sekali ditolak', function () {
    DB::transaction(function () {
        expect(fn () => $this->service->consume($this->branch, $this->product, '1.0000', $this->reference, StockMutationType::Sale))
            ->toThrow(InsufficientStockException::class);
    });
});

it('reverseForReference membalik konsumsi — mengembalikan qty_remaining batch asal', function () {
    DB::transaction(function () {
        $receiptDoc = Branch::factory()->create();
        $saleDoc = $this->reference;

        $batch = $this->service->receive($this->branch, $this->product, '10.0000', '15000.00', now(), $receiptDoc, StockMutationType::Receipt);
        $this->service->consume($this->branch, $this->product, '4.0000', $saleDoc, StockMutationType::Sale);

        expect($batch->fresh()->qty_remaining)->toEqual('6.0000');

        $voidDoc = Branch::factory()->create();
        $this->service->reverseForReference($saleDoc, $voidDoc);

        expect($batch->fresh()->qty_remaining)->toEqual('10.0000')
            ->and($this->service->availableQuantity($this->branch, $this->product))->toEqual('10.0000');

        $reversal = StockMutation::query()
            ->where('reference_type', $voidDoc->getMorphClass())
            ->where('reference_id', $voidDoc->id)
            ->sole();
        expect($reversal->mutation_type)->toBe(StockMutationType::VoidReversal)
            ->and((string) $reversal->quantity)->toEqual('4.0000');
    });
});

it('reverseForReference membalik penerimaan yang belum tersentuh', function () {
    DB::transaction(function () {
        $batch = $this->service->receive($this->branch, $this->product, '8.0000', '15000.00', now(), $this->reference, StockMutationType::Receipt);

        $voidDoc = Branch::factory()->create();
        $this->service->reverseForReference($this->reference, $voidDoc);

        expect($batch->fresh()->qty_remaining)->toEqual('0.0000')
            ->and($this->service->availableQuantity($this->branch, $this->product))->toEqual('0.0000');
    });
});

it('reverseForReference menolak membalik penerimaan yang sudah terpakai sebagian', function () {
    DB::transaction(function () {
        $this->service->receive($this->branch, $this->product, '8.0000', '15000.00', now(), $this->reference, StockMutationType::Receipt);
        $this->service->consume($this->branch, $this->product, '3.0000', $this->reference, StockMutationType::Sale);

        $voidDoc = Branch::factory()->create();

        expect(fn () => $this->service->reverseForReference($this->reference, $voidDoc))
            ->toThrow(InsufficientStockException::class);
    });
});

it('menolak dipanggil di luar transaksi', function () {
    // beforeEach sudah membuka transaksi (untuk isolasi data uji) — batalkan
    // dulu agar transactionLevel() benar-benar 0, lalu buka lagi supaya
    // afterEach tetap punya transaksi untuk di-rollback.
    DB::rollBack();

    expect(fn () => $this->service->receive($this->branch, $this->product, '1.0000', '1000.00', now(), $this->reference, StockMutationType::Receipt))
        ->toThrow(LogicException::class);

    DB::beginTransaction();
});

it('menolak kuantitas atau unit cost nol/negatif', function () {
    DB::transaction(function () {
        expect(fn () => $this->service->receive($this->branch, $this->product, '0.0000', '1000.00', now(), $this->reference, StockMutationType::Receipt))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $this->service->receive($this->branch, $this->product, '1.0000', '0.00', now(), $this->reference, StockMutationType::Receipt))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $this->service->consume($this->branch, $this->product, '-1.0000', $this->reference, StockMutationType::Sale))
            ->toThrow(InvalidArgumentException::class);
    });
});
