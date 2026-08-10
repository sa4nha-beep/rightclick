<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Infrastructure\Persistence\Models\CashierShift;
use Illuminate\Support\Facades\DB;

/**
 * Void shift kasir yang sudah ditutup (T4.1, R4) — satu-satunya jalur
 * koreksi atas kesalahan input penutupan (mis. salah hitung kas fisik).
 * Tidak menyentuh `StockLedgerService` — shift bukan dokumen inventory,
 * tidak ada mutasi untuk dibalik. Kasir tetap harus membuka shift BARU
 * secara terpisah untuk melanjutkan operasi (bukan otomatis).
 *
 * `OutboxService` (T5.7 retrofit, simpul kritis): `cashier_shift.voided`
 * — WAJIB berada dalam transaksi (kontrak `OutboxService::record()`), jadi
 * `execute()` di sini dibungkus `DB::transaction()` yang sebelumnya TIDAK
 * ada (aksi ini sebelumnya hanya satu `save()` tunggal, aman tanpa
 * transaksi eksplisit — celah kecil yang tertutup saat retrofit ini).
 */
final class VoidCashierShiftAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(CashierShift $shift, string $reason): CashierShift
    {
        return DB::transaction(function () use ($shift, $reason) {
            $this->documentStates->void($shift, $reason);
            $this->outbox->record($shift->branch, $shift, 'cashier_shift.voided');

            return $shift->fresh();
        });
    }
}
