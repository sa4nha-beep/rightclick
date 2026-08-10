<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Finance\Enums\CashEntryType;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashEntry;
use App\Infrastructure\Persistence\Scopes\BranchScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Satu-satunya penulis `cash_entries` (AC-21) — simpul kritis T5.4, pola
 * persis `StockLedgerService` (R1/T3.2) diterapkan ke konteks kas.
 * Ditegakkan `tests/Arch/CashEntrySingleWriterTest.php`.
 *
 * Dipanggil dari `FinalizeSaleAction` (kas masuk, pembayaran tunai
 * penjualan) dan `RecordPurchasePaymentAction` (kas keluar, pembayaran
 * tunai hutang) — retrofit T5.4 ke dua action yang sudah ada, sama seperti
 * `StockLedgerService::receive()` sendiri di-retrofit ke goods receipt
 * T5.2 setelah disebut "kelak" sejak T3.2.
 *
 * Seluruh pemanggilan WAJIB berada di dalam transaksi milik dokumen —
 * sama kontrak `StockLedgerService`/`DocumentNumberService`.
 */
final class CashLedgerService
{
    /**
     * Catat satu entri kas. `$amount` BERTANDA — positif untuk kas masuk,
     * negatif untuk kas keluar (sama konvensi `stock_mutations.quantity`).
     * `$reference` WAJIB (AC-21) — parameter non-nullable di level tipe PHP
     * adalah pertahanan pertama, dipertegas kolom NOT NULL di database.
     */
    public function record(
        Branch $branch,
        string $amount,
        CashEntryType $entryType,
        CarbonInterface $occurredAt,
        Model $reference,
    ): CashEntry {
        $this->assertInsideTransaction();
        $this->assertNonZero($amount);

        return CashEntry::create([
            'branch_id' => $branch->getKey(),
            'entry_type' => $entryType,
            'amount' => $amount,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'occurred_at' => $occurredAt,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);
    }

    /**
     * Balikkan seluruh entri kas yang merujuk `$originalReference` —
     * dipanggil saat dokumen (mis. `Sale`) di-void. TIDAK menghapus atau
     * mengubah baris lama (§16 peringatan #9, pola sama
     * `StockLedgerService::reverseForReference()`): menerbitkan entri baru
     * berarah berlawanan yang merujuk `$voidReference`.
     */
    public function reverseForReference(Model $originalReference, Model $voidReference): void
    {
        $this->assertInsideTransaction();

        $entries = CashEntry::withoutGlobalScope(BranchScope::class)
            ->where('reference_type', $originalReference->getMorphClass())
            ->where('reference_id', $originalReference->getKey())
            ->get();

        foreach ($entries as $entry) {
            $branch = Branch::findOrFail($entry->branch_id);

            $this->record(
                $branch,
                bcmul((string) $entry->amount, '-1', 2),
                CashEntryType::VoidReversal,
                now(),
                $voidReference,
            );
        }
    }

    /**
     * Saldo kas kini di satu cabang — SUM `amount` (bertanda) atas seluruh
     * entri. Tanpa cache turunan (beda dari `stock_balances`) — CLAUDE.md
     * §7 tidak mendaftarkan tabel cache kas LOCAL mana pun; volume entri
     * kas jauh lebih kecil dari mutasi stok, penjumlahan langsung memadai.
     */
    public function balance(Branch $branch): string
    {
        $sum = CashEntry::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branch->getKey())
            ->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }

    private function assertNonZero(string $amount): void
    {
        if (bccomp($amount, '0', 2) === 0) {
            throw new InvalidArgumentException('Jumlah entri kas tidak boleh nol.');
        }
    }

    private function assertInsideTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(
                'CashLedgerService harus dipanggil di dalam transaksi dokumen, sama seperti StockLedgerService.',
            );
        }
    }
}
