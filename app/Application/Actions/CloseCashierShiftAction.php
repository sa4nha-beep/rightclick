<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
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
 *
 * Penutup gap AC-16 asli (`HS-TASKS-RIGHTCLICK-v1.1` T4.2: "hitung per
 * pecahan") — `closing_cash_counted` TIDAK LAGI diterima sebagai satu
 * angka agregat, melainkan DIHITUNG dari baris `cashier_shift_counts`
 * (`denomination` × `quantity` per pecahan, dijumlahkan di sini). Baris
 * breakdown ditulis ke tabel TERPISAH (bukan kolom di `cashier_shifts`
 * sendiri) — tidak tersentuh guard `HasDocumentState` sama sekali karena
 * dibuat SEBELUM `finalize()` dipanggil.
 */
final class CloseCashierShiftAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly OutboxService $outbox,
    ) {}

    /**
     * @param  array<int, array{denomination: string, quantity: int}>  $denominationCounts
     */
    public function execute(CashierShift $shift, array $denominationCounts): CashierShift
    {
        return DB::transaction(function () use ($shift, $denominationCounts) {
            if ($shift->state !== DocumentState::Draft) {
                throw new CashierShiftException('Shift ini sudah tidak terbuka — tidak dapat ditutup lagi.');
            }

            if ($denominationCounts === []) {
                throw new CashierShiftException('Hitung kas per pecahan wajib diisi minimal satu baris.');
            }

            $closingCashCounted = '0';
            $rows = [];

            foreach ($denominationCounts as $count) {
                $denomination = (string) $count['denomination'];
                $quantity = (int) $count['quantity'];

                if (bccomp($denomination, '0', 2) <= 0) {
                    throw new CashierShiftException('Pecahan uang harus lebih besar dari nol.');
                }

                if ($quantity < 0) {
                    throw new CashierShiftException('Jumlah lembar/koin tidak boleh negatif.');
                }

                $subtotal = bcmul($denomination, (string) $quantity, 2);
                $closingCashCounted = bcadd($closingCashCounted, $subtotal, 2);

                $rows[] = ['denomination' => $denomination, 'quantity' => $quantity, 'subtotal' => $subtotal];
            }

            $expected = $this->computeExpectedCash($shift);

            $shift->closing_cash_expected = $expected;
            $shift->closing_cash_counted = $closingCashCounted;
            $shift->variance = bcsub($closingCashCounted, $expected, 2);
            $shift->document_number = $this->documentNumbers->next(DocumentType::CashierShift, $shift->branch);
            $shift->save();

            $shift->counts()->createMany($rows);

            $this->documentStates->finalize($shift);

            $this->outbox->record($shift->branch, $shift, 'cashier_shift.finalized', ['counts']);

            return $shift->fresh(['counts']);
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
