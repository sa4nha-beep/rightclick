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
 * Ditolak bila `Payable` terkait sudah punya `paid_amount > 0` — pembayaran
 * bersifat immutable tanpa mekanisme koreksi individual, jadi membatalkan
 * faktur yang sudah menerima cicilan akan meninggalkan pembayaran yang
 * tidak jelas dasarnya. Pola sama `VoidGoodsReceiptAction` (ditolak selama
 * ada faktur aktif) diterapkan satu langkah lebih jauh ke rantai dokumen.
 * Setelah guard lolos, baris `Payable` ikut di-soft-delete (penutup gap
 * FR-M11a-05).
 *
 * `Payable` yang baru di-soft-delete DILAMPIRKAN LEWAT `$extra` — sama
 * alasan `VoidSaleAction` (lihat docblocknya untuk detail bug
 * `OutboxService::record()`/`refresh()`/`SoftDeletes` yang dihindari).
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
            $payable = $purchaseInvoice->payable;

            if ($payable !== null && bccomp((string) $payable->paid_amount, '0', 2) > 0) {
                throw new PurchaseInvoiceValidationException(
                    'Faktur ini sudah menerima pembayaran — tidak dapat dibatalkan selama ada cicilan tercatat.',
                );
            }

            $payable?->delete();

            $this->documentStates->void($purchaseInvoice, $reason);

            $extra = $payable !== null ? ['payable' => $payable->attributesToArray()] : [];
            $this->outbox->record($purchaseInvoice->branch, $purchaseInvoice, 'purchase_invoice.voided', extra: $extra);

            return $purchaseInvoice->fresh();
        });
    }
}
