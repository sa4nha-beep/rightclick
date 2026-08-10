<?php

declare(strict_types=1);

namespace App\Domain\Procurement\Exceptions;

use DomainException;

/**
 * Pelanggaran validasi purchase order pada saat finalisasi (T5.1) — baris
 * kosong atau pemasok tidak valid. Dilempar di dalam transaksi finalisasi
 * sehingga seluruh perubahan (termasuk nomor dokumen yang sudah diambil)
 * ikut rollback.
 *
 * `DomainException` (SPL bawaan PHP), konsisten dengan
 * `SaleValidationException`/`StockDocumentValidationException`.
 */
final class PurchaseOrderValidationException extends DomainException {}
