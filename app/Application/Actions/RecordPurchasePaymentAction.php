<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Domain\Procurement\Exceptions\PurchaseInvoiceValidationException;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use App\Infrastructure\Persistence\Models\PurchasePayment;
use Illuminate\Support\Facades\DB;

/**
 * Catat satu cicilan/pembayaran hutang (T5.3) atas `PurchaseInvoice` yang
 * SUDAH final. BEDA dari pola `FinalizeXAction` lain — tidak menyentuh
 * `DocumentStateService` sama sekali, karena `PurchaseInvoice` induk sudah
 * final dan TETAP final (R4); dokumen ini hanya menambah baris anak baru.
 *
 * `PaymentMethod` dipakai ulang langsung dari `App\Domain\Sales\Enums` —
 * BUKAN diduplikasi ke `Domain\Procurement`. Secara konsep enum ini sudah
 * tidak spesifik-Sales sejak dipakai di sini (bentuk pembayaran yang sama
 * berlaku dua arah, uang masuk maupun keluar); idealnya dipromosikan ke
 * `Domain\Shared` saat ada kesempatan pembersihan lintas modul — TIDAK
 * dilakukan di sini karena akan menyentuh ~10 berkas Sales yang tidak
 * terkait cakupan T5.3 (SalePayment/FinalizeSaleAction/CloseCashierShiftAction/
 * Filament SaleResource/tests), risiko regresi tidak sepadan dengan
 * manfaat murni-kerapian untuk task ini. Ditandai eksplisit untuk
 * direkonsiliasi bersama pembersihan Domain lainnya.
 *
 * TIDAK menulis `cash_entries` (T5.4 belum ada) — penyambungan ke ledger
 * kas adalah pekerjaan T5.4 (lihat catatan Fase 5 §11 CLAUDE.md), yang
 * kemungkinan akan retrofit aksi ini untuk juga menulis kas keluar bila
 * `method=cash`, sama pola `outbox_events` (T5.7) akan retrofit seluruh
 * action finalize dokumen SYNCED.
 */
final class RecordPurchasePaymentAction
{
    /**
     * @param  array{method: string, amount: string, reference_no?: string|null}  $payment
     */
    public function execute(PurchaseInvoice $purchaseInvoice, array $payment): PurchasePayment
    {
        return DB::transaction(function () use ($purchaseInvoice, $payment) {
            $invoice = PurchaseInvoice::query()
                ->whereKey($purchaseInvoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->state !== DocumentState::Final) {
                throw new PurchaseInvoiceValidationException(
                    'Hanya faktur berstatus final yang dapat menerima pembayaran.',
                );
            }

            $amount = (string) $payment['amount'];

            if (bccomp($amount, '0', 2) <= 0) {
                throw new PurchaseInvoiceValidationException('Jumlah pembayaran harus lebih besar dari nol.');
            }

            $projectedPaid = bcadd($invoice->amountPaid(), $amount, 2);

            if (bccomp($projectedPaid, (string) $invoice->total_amount, 2) > 0) {
                throw new PurchaseInvoiceValidationException(sprintf(
                    'Pembayaran Rp%s melebihi sisa hutang Rp%s pada faktur ini.',
                    $amount,
                    $invoice->balanceDue(),
                ));
            }

            return $invoice->payments()->create([
                'method' => PaymentMethod::from($payment['method'])->value,
                'amount' => $amount,
                'reference_no' => $payment['reference_no'] ?? null,
            ]);
        });
    }
}
