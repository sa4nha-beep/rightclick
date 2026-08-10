<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Sync\Enums\OutboxEventStatus;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashEntry;
use App\Infrastructure\Persistence\Models\OutboxEvent;
use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Models\StockMutation;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya penulis `outbox_events` (T5.7, SIMPUL KRITIS) — pola persis
 * `StockLedgerService`/`CashLedgerService`: kontrak transaksi wajib
 * (`assertInsideTransaction()`), ditegakkan `tests/Arch/OutboxEventSingleWriterTest.php`.
 *
 * Dipanggil dari SETIAP `Finalize*Action`/`Void*Action`/`Record*Action` atas
 * dokumen SYNCED (Sales, Inventory, Procurement) — di TRANSAKSI YANG SAMA
 * dengan dokumen itu sendiri.
 *
 * PAYLOAD (T5.8 — diperkaya dari desain awal T5.7 yang sengaja hanya
 * `attributesToArray()` dokumen induk): setiap event membawa SNAPSHOT
 * RELASIONAL LENGKAP yang dibutuhkan HQ untuk merekonstruksi SELURUH baris
 * SYNCED yang tercipta di transaksi yang sama, bukan hanya kolom dokumen
 * induknya —
 *
 *  - `$relations` — nama relasi Eloquent pada `$document` yang dilampirkan
 *    apa adanya (mis. `items`/`payments`/`lines`) — baris anak yang
 *    dimiliki LANGSUNG oleh dokumen lewat foreign key eksplisit.
 *  - `stock_mutations`/`stock_batches`/`cash_entries` DILAMPIRKAN OTOMATIS
 *    untuk SEMUA event — dicari lewat `reference_type`/`reference_id` yang
 *    menunjuk `$document`, pola referensi polimorfik yang SUDAH seragam di
 *    `StockLedgerService`/`CashLedgerService` untuk SETIAP dokumen yang
 *    menyentuh ledger — tidak perlu relasi Eloquent bertujuan-khusus per
 *    model, satu query generik berlaku untuk semuanya. `stock_batches`
 *    diturunkan dari `stock_mutations.stock_batch_id` yang ikut terlampir
 *    (batch BARU yang lahir dari `receive()` harus ikut disinkronkan, tidak
 *    cukup mutasinya saja).
 *  - `$extra` — escape hatch untuk kasus di mana entitas terkait
 *    MERUJUK REFERENSI LAIN, bukan `$document` itu sendiri — SATU-SATUNYA
 *    pemakai saat ini: `RecordPurchasePaymentAction`/`RecordReceivablePaymentAction`,
 *    di mana `CashEntry` yang tercipta merujuk `PurchaseInvoice`/`Sale`
 *    (dokumen induk), BUKAN `PurchasePayment`/`ReceivablePayment` (aggregate
 *    event ini) — auto-attach di atas tidak akan menemukannya karena
 *    mencari berdasar `$document`, jadi caller melampirkannya manual.
 *
 * Skema payload penuh untuk penanganan `deferred` (CLAUDE.md §8 — event
 * yang merujuk entitas yang belum tiba di HQ, mis. `sale.finalized`
 * merujuk `batch_id` dari `goods_receipt.finalized`) masih pekerjaan
 * konsumen (`SyncEventProcessor`, lihat bawah) untuk memutuskan APA yang
 * dianggap "belum tiba" — payload di sini hanya menyediakan datanya.
 */
final class OutboxService
{
    /**
     * Catat satu event outbox. `$eventType` berpola `{dokumen}.{aksi}`,
     * mis. `sale.finalized`, `purchase_order.voided` (CLAUDE.md §8, contoh
     * `sale.finalized`/`goods_receipt.finalized`).
     *
     * @param  array<int, string>  $relations
     * @param  array<string, mixed>  $extra
     */
    public function record(
        Branch $branch,
        Model $document,
        string $eventType,
        array $relations = [],
        array $extra = [],
    ): OutboxEvent {
        $this->assertInsideTransaction();

        // WAJIB refresh SEBELUM attributesToArray() — bug nyata ditemukan
        // saat verifikasi T5.8: pemanggil (mis. FinalizeSaleAction::applyAndFinalize())
        // memberi $document yang atribut in-memory-nya BISA TIDAK LENGKAP.
        // Kolom dengan DEFAULT di level database (mis. sales.discount_amount
        // default 0) atau yang bernilai NULL dan TIDAK PERNAH disentuh
        // eksplisit lewat fill()/penugasan properti (mis. partner_id pada
        // walk-in) TIDAK PERNAH masuk ke $attributes Eloquent in-memory —
        // BUKAN sekadar bernilai null, KUNCINYA SENDIRI HILANG dari
        // attributesToArray(). Tanpa refresh(), payload kehilangan kolom
        // itu sama sekali — ditemukan lewat baris `sales` hasil
        // DB::table('sales')->upsert(...) yang tidak lengkap saat
        // SyncEventApplier mencoba merekonstruksi dari payload semacam itu.
        $document->refresh();

        $payload = $document->attributesToArray();

        foreach ($relations as $relationName) {
            $document->loadMissing($relationName);
            $related = $document->getRelation($relationName);

            $payload[$relationName] = $related instanceof EloquentCollection
                ? $related->map(fn (Model $model): array => $model->attributesToArray())->all()
                : $related?->attributesToArray();
        }

        $mutations = StockMutation::withoutGlobalScope(BranchScope::class)
            ->where('reference_type', $document->getMorphClass())
            ->where('reference_id', $document->getKey())
            ->get();

        if ($mutations->isNotEmpty()) {
            $payload['stock_mutations'] = $mutations->map(fn (StockMutation $m): array => $m->attributesToArray())->all();

            $batches = StockBatch::withoutGlobalScope(BranchScope::class)
                ->whereIn('id', $mutations->pluck('stock_batch_id')->unique())
                ->get();
            $payload['stock_batches'] = $batches->map(fn (StockBatch $b): array => $b->attributesToArray())->all();
        }

        $cashEntries = CashEntry::withoutGlobalScope(BranchScope::class)
            ->where('reference_type', $document->getMorphClass())
            ->where('reference_id', $document->getKey())
            ->get();

        if ($cashEntries->isNotEmpty()) {
            $payload['cash_entries'] = $cashEntries->map(fn (CashEntry $e): array => $e->attributesToArray())->all();
        }

        $payload = array_merge($payload, $extra);

        return OutboxEvent::create([
            'branch_id' => $branch->getKey(),
            'event_type' => $eventType,
            'aggregate_type' => $document->getMorphClass(),
            'aggregate_id' => $document->getKey(),
            'payload' => $payload,
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
