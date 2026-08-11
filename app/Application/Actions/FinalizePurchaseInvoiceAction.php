<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentNumberService;
use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Domain\Procurement\Exceptions\PurchaseInvoiceValidationException;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\DocumentType;
use App\Infrastructure\Persistence\Models\Payable;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Finalisasi faktur pembelian (T5.2). TIDAK menyentuh `StockLedgerService`
 * sama sekali — stok sudah bergerak saat `GoodsReceipt` difinalisasi
 * (simpul kritis sebenarnya). Aksi ini murni mengunci `total_amount` dari
 * `goods_receipts.total_amount` (HARUS sama — faktur tidak boleh mengklaim
 * nilai berbeda dari yang benar-benar sudah masuk ledger) dan menerbitkan
 * nomor dokumen internal.
 *
 * TIDAK ADA alur ambang/`ApprovalService` — §10 tidak menetapkan TH untuk
 * faktur pembelian. Finalisasi murni digerbang
 * `PurchaseInvoicePolicy::finalize()` (`approve_goods_receipt`).
 *
 * Penutup gap FR-M11a-05 (`HS-DB-RIGHTCLICK-v1.0` §4.6): satu baris
 * `Payable` dibuat DI SINI untuk SETIAP faktur yang difinalisasi (beda dari
 * `Receivable` sisi AR yang kondisional — tidak ada konsep "DP" pembelian
 * di T5.2, `total_amount` penuh SELALU jadi hutang awal).
 */
final class FinalizePurchaseInvoiceAction
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly DocumentStateService $documentStates,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(PurchaseInvoice $purchaseInvoice): PurchaseInvoice
    {
        return DB::transaction(function () use ($purchaseInvoice) {
            $purchaseInvoice->loadMissing(['branch', 'goodsReceipt']);

            $this->validateStructure($purchaseInvoice);

            $purchaseInvoice->total_amount = (string) $purchaseInvoice->goodsReceipt->total_amount;
            $purchaseInvoice->document_number = $this->documentNumbers->next(DocumentType::PurchaseInvoice, $purchaseInvoice->branch);
            $purchaseInvoice->save();

            Payable::create([
                'branch_id' => $purchaseInvoice->branch_id,
                'purchase_invoice_id' => $purchaseInvoice->id,
                'partner_id' => $purchaseInvoice->partner_id,
                'original_amount' => $purchaseInvoice->total_amount,
                'paid_amount' => '0.00',
                'outstanding_amount' => $purchaseInvoice->total_amount,
                'payment_status' => PaymentStatus::Unpaid,
            ]);

            $this->documentStates->finalize($purchaseInvoice);

            $this->outbox->record($purchaseInvoice->branch, $purchaseInvoice, 'purchase_invoice.finalized', ['payable']);

            return $purchaseInvoice->fresh();
        });
    }

    private function validateStructure(PurchaseInvoice $purchaseInvoice): void
    {
        if (blank(trim((string) $purchaseInvoice->invoice_number))) {
            throw new PurchaseInvoiceValidationException('Nomor faktur pemasok wajib diisi.');
        }

        if ($purchaseInvoice->goodsReceipt->state !== DocumentState::Final) {
            throw new PurchaseInvoiceValidationException(
                'Penerimaan barang terkait belum difinalisasi — faktur tidak dapat diproses sebelum barang benar-benar diterima.',
            );
        }
    }
}
