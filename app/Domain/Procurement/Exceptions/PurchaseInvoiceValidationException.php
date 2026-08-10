<?php

declare(strict_types=1);

namespace App\Domain\Procurement\Exceptions;

use DomainException;

/**
 * Pelanggaran validasi faktur pembelian pada saat finalisasi (T5.2) —
 * penerimaan barang belum final, atau nomor faktur kosong. Dilempar di
 * dalam transaksi finalisasi sehingga seluruh perubahan (termasuk nomor
 * dokumen yang sudah diambil) ikut rollback.
 *
 * `DomainException` (SPL bawaan PHP), konsisten dengan
 * `PurchaseOrderValidationException`/`SaleValidationException`.
 */
final class PurchaseInvoiceValidationException extends DomainException {}
