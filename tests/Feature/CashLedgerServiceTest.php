<?php

declare(strict_types=1);

use App\Application\Services\CashLedgerService;
use App\Domain\Finance\Enums\CashEntryType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * T5.4 — CashLedgerService, simpul kritis (AC-21). Sama pola
 * `StockLedgerServiceTest` — `$reference` di seluruh test ini adalah
 * `Branch` sungguhan dipakai murni sebagai "dokumen apa pun" (morph
 * target); mekanisme ledger tidak peduli jenis dokumennya.
 */
beforeEach(function () {
    DB::beginTransaction();
    $this->service = app(CashLedgerService::class);
    $this->branch = Branch::factory()->create();
    $this->reference = Branch::factory()->create();
});

afterEach(function () {
    DB::rollBack();
});

it('record menerbitkan entri kas masuk (positif) dan menaikkan saldo', function () {
    DB::transaction(function () {
        $entry = $this->service->record($this->branch, '50000.00', CashEntryType::SalePayment, now(), $this->reference);

        expect((string) $entry->amount)->toEqual('50000.00')
            ->and($entry->reference_type)->toBe($this->reference->getMorphClass())
            ->and($entry->reference_id)->toBe($this->reference->id);

        expect($this->service->balance($this->branch))->toEqual('50000.00');
    });
});

it('record menerbitkan entri kas keluar (negatif) dan menurunkan saldo', function () {
    DB::transaction(function () {
        $this->service->record($this->branch, '-30000.00', CashEntryType::PurchasePayment, now(), $this->reference);

        expect($this->service->balance($this->branch))->toEqual('-30000.00');
    });
});

it('reverseForReference membalik seluruh entri atas satu dokumen', function () {
    DB::transaction(function () {
        $this->service->record($this->branch, '50000.00', CashEntryType::SalePayment, now(), $this->reference);
        $this->service->record($this->branch, '20000.00', CashEntryType::SalePayment, now(), $this->reference);
    });

    expect($this->service->balance($this->branch))->toEqual('70000.00');

    DB::transaction(fn () => $this->service->reverseForReference($this->reference, $this->reference));

    expect($this->service->balance($this->branch))->toEqual('0.00');

    $reversalCount = CashEntry::query()
        ->where('reference_type', $this->reference->getMorphClass())
        ->where('reference_id', $this->reference->id)
        ->where('entry_type', CashEntryType::VoidReversal->value)
        ->count();

    expect($reversalCount)->toBe(2);
});

it('menolak dipanggil di luar transaksi', function () {
    DB::rollBack();

    expect(fn () => $this->service->record($this->branch, '10000.00', CashEntryType::SalePayment, now(), $this->reference))
        ->toThrow(LogicException::class);

    DB::beginTransaction();
});

it('menolak jumlah nol', function () {
    DB::transaction(function () {
        expect(fn () => $this->service->record($this->branch, '0.00', CashEntryType::SalePayment, now(), $this->reference))
            ->toThrow(InvalidArgumentException::class);
    });
});

it('AC-21 — database menolak baris tanpa reference_type/reference_id', function () {
    expect(function () {
        DB::table('cash_entries')->insert([
            'id' => (string) Str::uuid7(),
            'branch_id' => $this->branch->id,
            'entry_type' => CashEntryType::SalePayment->value,
            'amount' => '10000.00',
            'reference_type' => null,
            'reference_id' => null,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});
