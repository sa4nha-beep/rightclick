<?php

declare(strict_types=1);

namespace App\Application\Services\Sync;

use App\Domain\Sync\Exceptions\SyncEventDeferredException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menerapkan SATU payload event outbox ke tabel SYNCED milik HQ (T5.8).
 * Registry generik berbasis TABEL, bukan 22 handler khusus per event —
 * bentuk payload sudah seragam sejak diperkaya `OutboxService` (T5.8):
 * kolom dokumen induk di level atas, baris anak sebagai array bertingkat
 * di bawah kunci relasi (`items`/`lines`/`payments`), ditambah
 * `stock_mutations`/`stock_batches`/`cash_entries` yang SELALU dicari
 * dengan pola yang sama untuk SEMUA jenis dokumen.
 *
 * Urutan upsert WAJIB: dokumen induk → baris anak langsung → `stock_batches`
 * → `stock_mutations` (FK ke `stock_batches`) → `cash_entries`. Urutan yang
 * sama persis dengan urutan penguncian CLAUDE.md §7, diterapkan di sisi
 * penerima.
 *
 * Pelanggaran FK constraint (SQLSTATE 23503 Postgres) DITANGKAP secara
 * KHUSUS dan diterjemahkan jadi `SyncEventDeferredException` — ini
 * MEKANISME NYATA di balik status `deferred` (CLAUDE.md §8: "`sale.finalized`
 * merujuk `batch_id` dari `goods_receipt.finalized`; bila urutannya
 * terbalik itu bukan kesalahan"). Pelanggaran lain (mis. NOT NULL/CHECK
 * constraint, tipe data tidak valid) dibiarkan menjalar sebagai kegagalan
 * genuine — `SyncEventProcessor` menerjemahkannya jadi `rejected`.
 */
final class SyncEventApplier
{
    /**
     * Registry {prefix event_type} => {tabel dokumen induk, peta kunci
     * relasi anak => tabel tujuan}. Kunci di sini adalah bagian SEBELUM
     * titik pertama `event_type` (mis. `sale` dari `sale.finalized`) — 12
     * jenis dokumen mencakup 22 event (finalized+voided berbagi entri yang
     * sama), BUKAN `aggregate_type` (yang berisi FQCN model penuh tanpa
     * morph map terkonfigurasi).
     *
     * @var array<string, array{table: string, children: array<string, string>}>
     */
    private const REGISTRY = [
        // 'receivable' (T5.7 lama tanpa anak) DITAMBAH menutup gap FR-M11a-05
        // (HS-DB-RIGHTCLICK-v1.0 §4.6) — relasi HasOne, BUKAN HasMany seperti
        // 'items'/'lines' lain di registry ini; lihat upsertChildren() untuk
        // penanganan bentuk payload tunggal (bukan list) yang berbeda.
        'sale' => ['table' => 'sales', 'children' => ['items' => 'sale_items', 'payments' => 'sale_payments', 'receivable' => 'receivables']],
        'sale_return' => ['table' => 'sale_returns', 'children' => ['lines' => 'sale_return_lines']],
        'cashier_shift' => ['table' => 'cashier_shifts', 'children' => ['counts' => 'cashier_shift_counts']],
        'stock_adjustment' => ['table' => 'stock_adjustments', 'children' => ['lines' => 'stock_adjustment_lines']],
        'stock_opname' => ['table' => 'stock_opnames', 'children' => ['lines' => 'stock_opname_lines']],
        'stock_transfer' => ['table' => 'stock_transfers', 'children' => ['lines' => 'stock_transfer_lines']],
        'stock_transfer_receipt' => ['table' => 'stock_transfer_receipts', 'children' => []],
        'purchase_order' => ['table' => 'purchase_orders', 'children' => ['lines' => 'purchase_order_lines']],
        'goods_receipt' => ['table' => 'goods_receipts', 'children' => ['lines' => 'goods_receipt_lines']],
        // 'payable' (HasOne, sama catatan 'receivable' di atas) — penutup gap FR-M11a-05.
        'purchase_invoice' => ['table' => 'purchase_invoices', 'children' => ['payable' => 'payables']],
        'purchase_payment' => ['table' => 'purchase_payments', 'children' => ['allocations' => 'purchase_payment_allocations']],
        'receivable_payment' => ['table' => 'receivable_payments', 'children' => ['allocations' => 'receivable_payment_allocations']],
    ];

    /**
     * Kunci payload universal yang BUKAN kolom dokumen induk maupun baris
     * anak langsung — ditangani generik untuk SEMUA jenis dokumen.
     */
    private const LEDGER_KEYS = ['stock_batches', 'stock_mutations', 'cash_entries'];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apply(string $eventType, array $payload): void
    {
        $documentType = explode('.', $eventType, 2)[0];

        if (! array_key_exists($documentType, self::REGISTRY)) {
            throw new RuntimeException("Jenis event tidak dikenal, tidak ada di registry: {$eventType}.");
        }

        $entry = self::REGISTRY[$documentType];

        try {
            $this->upsertParent($entry['table'], $payload, $entry['children']);
            $this->upsertChildren($entry['children'], $payload);
            $this->upsertLedgerRows($payload);
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                throw new SyncEventDeferredException(
                    "Event {$eventType} bergantung pada entitas yang belum tiba di HQ: {$e->getMessage()}",
                    previous: $e,
                );
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $children
     */
    private function upsertParent(string $table, array $payload, array $children): void
    {
        $excludeKeys = array_merge(array_keys($children), self::LEDGER_KEYS);
        $row = array_diff_key($payload, array_flip($excludeKeys));

        DB::table($table)->upsert([$row], ['id']);
    }

    /**
     * @param  array<string, string>  $children
     * @param  array<string, mixed>  $payload
     */
    private function upsertChildren(array $children, array $payload): void
    {
        foreach ($children as $relationKey => $table) {
            $rows = $payload[$relationKey] ?? null;

            if (! is_array($rows) || $rows === []) {
                continue;
            }

            // Relasi HasOne (mis. 'receivable'/'payable', FR-M11a-05) dikirim
            // OutboxService sebagai SATU baris asosiatif (kunci nama kolom),
            // BUKAN list — beda dari relasi HasMany ('items'/'lines') yang
            // sudah berbentuk list of associative array. `array_is_list()`
            // membedakan keduanya tanpa perlu tahu jenis relasi eksplisit.
            if (! array_is_list($rows)) {
                $rows = [$rows];
            }

            DB::table($table)->upsert($rows, ['id']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertLedgerRows(array $payload): void
    {
        if (! empty($payload['stock_batches'])) {
            DB::table('stock_batches')->upsert($payload['stock_batches'], ['id']);
        }

        if (! empty($payload['stock_mutations'])) {
            DB::table('stock_mutations')->upsert($payload['stock_mutations'], ['id']);
        }

        if (! empty($payload['cash_entries'])) {
            DB::table('cash_entries')->upsert($payload['cash_entries'], ['id']);
        }
    }

    /**
     * SQLSTATE 23503 = `foreign_key_violation` (Postgres). `QueryException::getCode()`
     * mengembalikan SQLSTATE sebagai string di driver PostgreSQL Laravel.
     */
    private function isForeignKeyViolation(QueryException $e): bool
    {
        return $e->getCode() === '23503';
    }
}
