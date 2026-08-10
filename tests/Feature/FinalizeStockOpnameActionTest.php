<?php

declare(strict_types=1);

use App\Application\Actions\FinalizeStockOpnameAction;
use App\Application\Services\StockLedgerService;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Inventory\Enums\StockOpnameType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\StockOpname;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    $this->action = app(FinalizeStockOpnameAction::class);
    $this->ledger = app(StockLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->product = Product::factory()->create();
});

afterEach(function () {
    DB::rollBack();
});

it('menolak finalisasi bila baris berselisih tanpa alasan — AC-12', function () {
    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::Periodic]);
    $opname->lines()->create([
        'product_id' => $this->product->id,
        'counted_qty' => '5.0000',
        'unit_cost' => '10000.00',
        'reason' => null,
    ]);

    expect(fn () => $this->action->execute($opname))->toThrow(StockDocumentValidationException::class);

    expect($opname->fresh()->state)->toBe(DocumentState::Draft)
        ->and($opname->fresh()->document_number)->toBeNull();
});

it('menerima baris berselisih naik dengan alasan — menerbitkan batch baru dan finalisasi', function () {
    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::Periodic]);
    $opname->lines()->create([
        'product_id' => $this->product->id,
        'counted_qty' => '8.0000',
        'unit_cost' => '15000.00',
        'reason' => 'Ditemukan saat stock opname rutin',
    ]);

    $result = $this->action->execute($opname);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($result->document_number)->not->toBeNull()
        ->and($result->document_number)->toContain('OPN');

    expect($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('8.0000');
});

it('mengonsumsi FIFO untuk baris berselisih turun dengan alasan', function () {
    DB::transaction(function () {
        $this->ledger->receive($this->branch, $this->product, '10.0000', '5000.00', now(), Branch::factory()->create(), StockMutationType::Receipt);
    });

    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::Periodic]);
    $opname->lines()->create([
        'product_id' => $this->product->id,
        'counted_qty' => '6.0000',
        'reason' => 'Susut',
    ]);

    $this->action->execute($opname);

    expect($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('6.0000');
});

it('tidak menghasilkan mutasi untuk baris tanpa selisih', function () {
    DB::transaction(function () {
        $this->ledger->receive($this->branch, $this->product, '4.0000', '5000.00', now(), Branch::factory()->create(), StockMutationType::Receipt);
    });

    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::Periodic]);
    $opname->lines()->create([
        'product_id' => $this->product->id,
        'counted_qty' => '4.0000',
        'reason' => null,
    ]);

    $result = $this->action->execute($opname);

    expect($result->state)->toBe(DocumentState::Final)
        ->and($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('4.0000');
});

it('opname saldo awal wajib mengisi unit_cost eksplisit — tidak mewarisi batch lama', function () {
    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::OpeningBalance]);
    $opname->lines()->create([
        'product_id' => $this->product->id,
        'counted_qty' => '20.0000',
        'unit_cost' => null,
        'reason' => 'Saldo awal',
    ]);

    expect(fn () => $this->action->execute($opname))->toThrow(StockDocumentValidationException::class);
});

it('opname berkala mewarisi unit_cost dari batch terbaru bila baris tidak mengisi sendiri', function () {
    DB::transaction(function () {
        $ref = Branch::factory()->create();
        $this->ledger->receive($this->branch, $this->product, '5.0000', '10000.00', now()->subDays(5), $ref, StockMutationType::Receipt);
        $this->ledger->receive($this->branch, $this->product, '5.0000', '25000.00', now()->subDay(), $ref, StockMutationType::Receipt);
    });

    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::Periodic]);
    $opname->lines()->create([
        'product_id' => $this->product->id,
        'counted_qty' => '12.0000',
        'unit_cost' => null,
        'reason' => 'Ditemukan lebih',
    ]);

    $this->action->execute($opname);

    expect($this->ledger->availableQuantity($this->branch, $this->product))->toEqual('12.0000');
});

it('berselisih naik tanpa batch sebelumnya dan tanpa unit_cost eksplisit ditolak', function () {
    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::Periodic]);
    $opname->lines()->create([
        'product_id' => $this->product->id,
        'counted_qty' => '3.0000',
        'unit_cost' => null,
        'reason' => 'Ditemukan',
    ]);

    expect(fn () => $this->action->execute($opname))->toThrow(StockDocumentValidationException::class);
});

it('T3.7 — produk serial berselisih naik wajib mengisi serial number sejumlah selisih', function () {
    $serialized = Product::factory()->create(['is_serialized' => true]);
    $opname = StockOpname::factory()->create(['branch_id' => $this->branch->id, 'type' => StockOpnameType::Periodic]);
    $opname->lines()->create([
        'product_id' => $serialized->id,
        'counted_qty' => '2.0000',
        'unit_cost' => '500000.00',
        'reason' => 'Ditemukan',
        'serial_numbers' => ['SN-A'],
    ]);

    expect(fn () => $this->action->execute($opname))->toThrow(StockDocumentValidationException::class);

    $opname->lines()->first()->update(['serial_numbers' => ['SN-A', 'SN-B']]);

    // Instance baru — panggilan execute() sebelumnya sudah meng-cache
    // relasi `lines` lewat loadMissing(); $opname lama tidak akan melihat
    // update di atas tanpa refetch.
    $result = $this->action->execute($opname->fresh());
    expect($result->state)->toBe(DocumentState::Final);
});
