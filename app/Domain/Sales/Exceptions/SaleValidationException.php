<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exceptions;

use DomainException;

/**
 * Pelanggaran validasi penjualan pada saat finalisasi (T4.1) — baris
 * kosong, shift tidak terbuka, atau total pembayaran tidak sama dengan
 * `total_amount`. Dilempar di dalam transaksi finalisasi sehingga seluruh
 * perubahan (termasuk nomor dokumen yang sudah diambil) ikut rollback.
 *
 * `DomainException` (SPL bawaan PHP), konsisten dengan
 * `StockDocumentValidationException`/`InsufficientStockException`.
 */
final class SaleValidationException extends DomainException {}
