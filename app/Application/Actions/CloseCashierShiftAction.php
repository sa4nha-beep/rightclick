<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Exceptions\CashierShiftException;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\SalePayment;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;

/**
 * Tutup shift kasir (T4.1, AC-16). `closing_cash_expected` dihitung dari
 * `opening_cash` + jumlah `sale_payments.method='cash'` atas seluruh
 * `Sale` FINAL milik shift ini — TIDAK mempercayai angka dari draft mana
 * pun, dihitung ulang di sini pada saat penutupan (sama filosofi TOCTOU
 * yang sudah ditegakkan `FinalizeStockOpnameAction`: `system_qty` dihitung
 * ulang saat finalisasi, bukan dipercaya dari draft).
 *
 * Begitu `finalize()` dipanggil, `HasDocumentState` mengunci seluruh field
 * shift ini — "selisih tidak dapat disesuaikan" (AC-16) ditegakkan
 * struktural, bukan lewat pengecekan manual di sini.
 */
final class CloseCashierShiftAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
    ) {}

    public function execute(CashierShift $shift, string $closingCashCounted): CashierShift
    {
        return DB::transaction(function () use ($shift, $closingCashCounted) {
            if ($shift->state !== DocumentState::Draft) {
                throw new CashierShiftException('Shift ini sudah tidak terbuka — tidak dapat ditutup lagi.');
            }

            if (bccomp($closingCashCounted, '0', 2) < 0) {
                throw new CashierShiftException('Kas fisik yang dihitung tidak boleh negatif.');
            }

            $expected = $this->computeExpectedCash($shift);

            $shift->closing_cash_expected = $expected;
            $shift->closing_cash_counted = $closingCashCounted;
            $shift->variance = bcsub($closingCashCounted, $expected, 2);
            $shift->document_number = $this->documentNumbers->next(DocumentType::CashierShift, $shift->branch);
            $shift->save();

            $this->documentStates->finalize($shift);

            return $shift->fresh();
        });
    }

    private function computeExpectedCash(CashierShift $shift): string
    {
        $saleIds = $shift->sales()
            ->withoutGlobalScope(BranchScope::class)
            ->where('state', DocumentState::Final)
            ->pluck('id');

        if ($saleIds->isEmpty()) {
            return (string) $shift->opening_cash;
        }

        $cashTotal = SalePayment::withoutGlobalScope(BranchScope::class)
            ->whereIn('sale_id', $saleIds)
            ->where('method', PaymentMethod::Cash->value)
            ->sum('amount');

        return bcadd((string) $shift->opening_cash, (string) $cashTotal, 2);
    }
}
