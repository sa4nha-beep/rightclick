<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\ApprovalService;
use App\Application\Services\CashLedgerService;
use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Application\Services\SerialNumberValidationService;
use App\Application\Services\StockLedgerService;
use App\Domain\Finance\Enums\CashEntryType;
use App\Domain\Inventory\Enums\StockMutationType;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Finalisasi penjualan (T4.1, diskon TH1/TH2 + DP ditambahkan T4.2).
 *
 * TH1/TH2 (CLAUDE.md §10) ditegakkan di sini, bukan Policy — sama alasan
 * `FinalizeStockAdjustmentAction`: nilai diskon baru diketahui dari data
 * dokumen, bukan sesuatu yang bisa digerbang permission tetap. Ambang
 * dipilih berdasarkan PERAN aktor (Kasir → TH1, Admin → TH2) — Owner
 * DIKECUALIKAN sama pola dengan TH3/TH3b (§10 membingkai ambang sebagai
 * batas SEBELUM eskalasi ke Owner). Melebihi ambang TIDAK menolak transaksi
 * (AP-01) — dokumen tetap `draft` dengan `Approval` tertunda,
 * `ApproveSaleDiscountAction` memanggil `applyAndFinalize()` setelah
 * disetujui, melewati pemeriksaan ambang.
 *
 * DP/piutang parsial (T4.2): pembayaran boleh KURANG dari `total_amount`
 * ASALKAN `partner_id` terisi (walk-in tanpa identitas tidak bisa punya
 * piutang — tidak ada yang bisa ditagih). Sisa dicatat di `balance_due`
 * pada dokumen ini sendiri — BUKAN tabel `receivables` terpisah (Fase 5,
 * lihat catatan migration `add_payment_tracking_to_sales_table`).
 * Pembayaran MELEBIHI total tetap ditolak (kembalian tunai adalah urusan
 * POS UI, T4.4, bukan `sale_payments` — lihat catatan migration
 * `sale_payments`).
 *
 * COGS (`unit_cost_snapshot`) diisi dari HASIL NYATA FIFO
 * `StockLedgerService::consume()` (`Collection<StockConsumption>`) — bukan
 * estimasi rata-rata seperti `FinalizeStockAdjustmentAction`, karena
 * `consume()` sudah dipanggil di sini dan hasilnya presisi, tidak perlu
 * diperkirakan dulu.
 *
 * Baris jasa (`service_id` terisi) TIDAK menyentuh `StockLedgerService` sama
 * sekali — jasa bukan barang, tidak ada mutasi stok atau COGS.
 *
 * `CashLedgerService` (T5.4 retrofit): setiap `sale_payments.method='cash'`
 * menerbitkan satu `CashEntry` kas MASUK yang merujuk `Sale` ini (bukan
 * baris `SalePayment` individual — pola sama `stock_mutations` yang selalu
 * menunjuk dokumen induk). Pembayaran non-tunai (kartu/transfer/QRIS) TIDAK
 * menyentuh kas fisik, jadi TIDAK menulis `CashEntry` sama sekali.
 *
 * `OutboxService` (T5.7 retrofit, simpul kritis): `sale.finalized` dicatat
 * di `applyAndFinalize()` — mencakup baik `execute()` langsung maupun
 * jalur `ApproveSaleDiscountAction`, satu titik untuk kedua alur.
 */
final class FinalizeSaleAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly StockLedgerService $stockLedger,
        private readonly ApprovalService $approvals,
        private readonly CashLedgerService $cashLedger,
        private readonly OutboxService $outbox,
        private readonly SerialNumberValidationService $serialNumbers,
    ) {}

    public function execute(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $sale->loadMissing(['branch', 'cashierShift', 'items.product', 'items.service', 'payments']);

            $this->validateStructure($sale);

            $actor = Auth::user();
            $actorId = $actor === null ? null : (string) $actor->id;

            if ($actor === null || ! $actor->hasRole('owner')) {
                $threshold = $actor !== null && $actor->hasRole('admin')
                    ? (string) (Setting::get('discount.max_admin') ?? '300000')
                    : (string) (Setting::get('discount.max_kasir') ?? '100000');

                if (bccomp((string) $sale->discount_amount, $threshold, 2) > 0) {
                    $this->approvals->request($sale, $actorId, $sale->branch_id);

                    return $sale->fresh(['items', 'payments']);
                }
            }

            return $this->applyAndFinalize($sale);
        });
    }

    /**
     * Terapkan ke ledger dan finalisasi — dipanggil `execute()` langsung
     * bila di bawah ambang, atau `ApproveSaleDiscountAction` setelah
     * Approval disetujui.
     */
    public function applyAndFinalize(Sale $sale): Sale
    {
        $sale->loadMissing(['branch', 'cashierShift', 'items.product', 'items.service', 'payments']);

        $this->validateStructure($sale);

        $subtotal = '0';
        foreach ($sale->items as $item) {
            $subtotal = bcadd($subtotal, (string) $item->line_total, 2);
        }

        $totalAmount = bcsub($subtotal, (string) $sale->discount_amount, 2);

        if (bccomp($totalAmount, '0', 2) < 0) {
            throw new SaleValidationException('Diskon tidak boleh melebihi subtotal.');
        }

        $paid = '0';
        foreach ($sale->payments as $payment) {
            $paid = bcadd($paid, (string) $payment->amount, 2);
        }

        $sale->payment_status = $this->resolvePaymentStatus($sale, $paid, $totalAmount);
        $sale->amount_paid = $paid;
        $sale->balance_due = bcsub($totalAmount, $paid, 2);
        $sale->subtotal = $subtotal;
        $sale->total_amount = $totalAmount;
        $sale->document_number = $this->documentNumbers->next(DocumentType::Sale, $sale->branch);
        $sale->save();

        foreach ($sale->payments as $payment) {
            if ($payment->method !== PaymentMethod::Cash) {
                continue;
            }

            $this->cashLedger->record(
                $sale->branch,
                (string) $payment->amount,
                CashEntryType::SalePayment,
                now(),
                $sale,
            );
        }

        foreach ($sale->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $consumptions = $this->stockLedger->consume(
                $sale->branch,
                $item->product,
                (string) $item->quantity,
                $sale,
                StockMutationType::Sale,
            );

            $totalCost = '0';
            foreach ($consumptions as $consumption) {
                $totalCost = bcadd($totalCost, bcmul($consumption->quantity, $consumption->unitCost, 2), 2);
            }

            $item->unit_cost_snapshot = bcdiv($totalCost, (string) $item->quantity, 2);
            $item->save();
        }

        $this->documentStates->finalize($sale);

        $this->outbox->record($sale->branch, $sale, 'sale.finalized', ['items', 'payments']);

        return $sale->fresh(['items', 'payments']);
    }

    /**
     * Total nol (diskon penuh) — LUNAS tanpa pembayaran. Selain itu, minimal
     * satu pembayaran wajib ada; melebihi total ditolak (bukan kembalian);
     * kurang dari total (DP) hanya diizinkan bila `partner_id` terisi.
     */
    private function resolvePaymentStatus(Sale $sale, string $paid, string $totalAmount): PaymentStatus
    {
        if (bccomp($totalAmount, '0', 2) === 0) {
            if (bccomp($paid, '0', 2) !== 0) {
                throw new SaleValidationException('Total penjualan nol tidak boleh memiliki pembayaran.');
            }

            return PaymentStatus::Paid;
        }

        if (bccomp($paid, '0', 2) <= 0) {
            throw new SaleValidationException('Penjualan wajib memiliki minimal satu pembayaran.');
        }

        if (bccomp($paid, $totalAmount, 2) > 0) {
            throw new SaleValidationException('Total pembayaran tidak boleh melebihi total penjualan.');
        }

        if (bccomp($paid, $totalAmount, 2) === 0) {
            return PaymentStatus::Paid;
        }

        if ($sale->partner_id === null) {
            throw new SaleValidationException(
                'DP/pembayaran sebagian memerlukan pelanggan teridentifikasi (partner) — walk-in tidak dapat memiliki piutang.',
            );
        }

        return PaymentStatus::Partial;
    }

    private function validateStructure(Sale $sale): void
    {
        if ($sale->items->isEmpty()) {
            throw new SaleValidationException('Penjualan tanpa baris tidak dapat difinalisasi.');
        }

        if ($sale->cashierShift === null || $sale->cashierShift->state !== DocumentState::Draft) {
            throw new SaleValidationException('Shift kasir terkait sudah tidak terbuka — penjualan tidak dapat difinalisasi.');
        }

        foreach ($sale->items as $item) {
            if (bccomp((string) $item->quantity, '0', 4) <= 0) {
                throw new SaleValidationException('Kuantitas baris penjualan harus lebih dari nol.');
            }

            // T4.9/UT5: wajib serial number untuk produk `is_serialized`, ditegakkan
            // di sini — SEBELUM mutasi stok/kas apa pun — bukan hanya di UI POS,
            // konsisten dengan cara `SerialNumberValidationService` sudah dipakai
            // finalize action lain (T3.7). Baris jasa (`product_id` null) dilewati.
            if ($item->product_id !== null) {
                $this->serialNumbers->validate($item->product, (string) $item->quantity, $item->serial_numbers);
            }
        }
    }
}
