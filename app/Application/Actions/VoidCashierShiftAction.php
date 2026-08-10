<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Infrastructure\Persistence\Models\CashierShift;

/**
 * Void shift kasir yang sudah ditutup (T4.1, R4) — satu-satunya jalur
 * koreksi atas kesalahan input penutupan (mis. salah hitung kas fisik).
 * Tidak menyentuh `StockLedgerService` — shift bukan dokumen inventory,
 * tidak ada mutasi untuk dibalik. Kasir tetap harus membuka shift BARU
 * secara terpisah untuk melanjutkan operasi (bukan otomatis).
 */
final class VoidCashierShiftAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
    ) {}

    public function execute(CashierShift $shift, string $reason): CashierShift
    {
        $this->documentStates->void($shift, $reason);

        return $shift->fresh();
    }
}
