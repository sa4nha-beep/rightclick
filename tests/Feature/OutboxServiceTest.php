<?php

declare(strict_types=1);

use App\Application\Services\OutboxService;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * T5.7, simpul kritis — OutboxService. Sama pola `StockLedgerServiceTest`/
 * `CashLedgerServiceTest` — `$document` di sini adalah `Branch` sungguhan
 * dipakai murni sebagai "dokumen apa pun" (morph target).
 */
beforeEach(function () {
    DB::beginTransaction();
    $this->service = app(OutboxService::class);
    $this->branch = Branch::factory()->create();
    $this->document = Branch::factory()->create(['name' => 'Dokumen Uji']);
});

afterEach(function () {
    DB::rollBack();
});

it('record menulis event dengan payload snapshot atribut dokumen', function () {
    DB::transaction(function () {
        $event = $this->service->record($this->branch, $this->document, 'test.finalized');

        expect($event->event_type)->toBe('test.finalized')
            ->and($event->aggregate_type)->toBe($this->document->getMorphClass())
            ->and($event->aggregate_id)->toBe($this->document->id)
            ->and($event->payload['name'])->toBe('Dokumen Uji')
            ->and($event->status->value)->toBe('pending');
    });
});

it('menolak dipanggil di luar transaksi', function () {
    DB::rollBack();

    expect(fn () => $this->service->record($this->branch, $this->document, 'test.finalized'))
        ->toThrow(LogicException::class);

    DB::beginTransaction();
});

it('id event bersifat unik per pemanggilan — dasar idempotency key', function () {
    DB::transaction(function () {
        $first = $this->service->record($this->branch, $this->document, 'test.finalized');
        $second = $this->service->record($this->branch, $this->document, 'test.finalized');

        expect($first->id)->not->toBe($second->id);
        expect(OutboxEvent::query()->where('aggregate_id', $this->document->id)->count())->toBe(2);
    });
});
