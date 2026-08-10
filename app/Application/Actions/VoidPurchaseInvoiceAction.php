<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Services\DocumentStateService;
use App\Application\Services\OutboxService;
use App\Domain\Procurement\Exceptions\PurchaseInvoiceValidationException;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Void faktur pembelian final (T5.2, R4). TIDAK memanggil
 * `StockLedgerService` — faktur tidak pernah menyentuh ledger stok (itu
 * urusan `GoodsReceipt`). Void faktur murni koreksi sisi finansial/AP
 * (mis. nomor faktur salah input) tanpa memengaruhi stok yang sudah
 * diterima.
 *
 * Ditolak bila sudah ada `purchase_payments` tercatat (T5.3) — pembayaran
 * bersifat immutable tanpa mekanisme koreksi individual (lihat catatan
 * migration `purchase_payments`), jadi membatalkan faktur yang sudah
 * menerima cicilan akan meninggalkan pembayaran yang tidak jelas
 * dasarnya. Pola sama `VoidGoodsReceiptAction` (ditolak selama ada faktur
 * aktif) diterapkan satu langkah lebih jauh ke rantai dokumen.
 *
 * `OutboxService` (T5.7 retrofit, simpul kritis): `purchase_invoice.voided`.
 */
final class VoidPurchaseInvoiceAction
{
    public function __construct(
        private readonly DocumentStateService $documentStates,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(PurchaseInvoice $purchaseInvoice, string $reason): PurchaseInvoice
    {
        return DB::transaction(function () use ($purchaseInvoice, $reason) {
            if ($purchaseInvoice->payments()->exists()) {
                throw new PurchaseInvoiceValidationException(
                    'Faktur ini sudah menerima pembayaran — tidak dapat dibatalkan selama ada cicilan tercatat.',
                );
            }

            $this->documentStates->void($purchaseInvoice, $reason);
            $this->outbox->record($purchaseInvoice->branch, $purchaseInvoice, 'purchase_invoice.voided');

            return $purchaseInvoice->fresh();
        });
    }
}
