<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Sync\Enums\OutboxEventStatus;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya penulis `outbox_events` (T5.7, SIMPUL KRITIS) — pola persis
 * `StockLedgerService`/`CashLedgerService`: kontrak transaksi wajib
 * (`assertInsideTransaction()`), ditegakkan `tests/Arch/OutboxEventSingleWriterTest.php`.
 *
 * Dipanggil dari SETIAP `Finalize*Action`/`Void*Action` atas dokumen
 * SYNCED (Sales, Inventory, Procurement) — di TRANSAKSI YANG SAMA dengan
 * dokumen itu sendiri. Bila dipanggil di dalam `applyAndFinalize()` yang
 * dipakai bersama oleh `execute()` DAN `Approve*Action`, satu pemanggilan
 * otomatis mencakup kedua jalur (finalize langsung vs melalui approval).
 *
 * Payload TIDAK menyertakan baris anak (`sale_items`/`sale_payments`/
 * `stock_mutations`/`cash_entries`) — satu event per TRANSISI dokumen
 * induk (lihat docblock migration/`OutboxEvent`), bukan per baris.
 */
final class OutboxService
{
    /**
     * Catat satu event outbox. `$eventType` berpola `{dokumen}.{aksi}`,
     * mis. `sale.finalized`, `purchase_order.voided` (CLAUDE.md §8, contoh
     * `sale.finalized`/`goods_receipt.finalized`).
     */
    public function record(Branch $branch, Model $document, string $eventType): OutboxEvent
    {
        $this->assertInsideTransaction();

        return OutboxEvent::create([
            'branch_id' => $branch->getKey(),
            'event_type' => $eventType,
            'aggregate_type' => $document->getMorphClass(),
            'aggregate_id' => $document->getKey(),
            'payload' => $document->attributesToArray(),
            'status' => OutboxEventStatus::Pending,
        ]);
    }

    private function assertInsideTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(
                'OutboxService harus dipanggil di dalam transaksi dokumen (T5.7) — '.
                'di luar transaksi, dokumen final bisa hilang tanpa jejak ke HQ, sama kontrak '.
                'StockLedgerService/CashLedgerService.',
            );
        }
    }
}
