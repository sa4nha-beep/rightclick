<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use DomainException;

/**
 * Pelanggaran validasi baris dokumen inventory pada saat finalisasi —
 * dipakai bersama oleh opname (T3.4, AC-12: "baris opname berselisih tanpa
 * alasan → penyimpanan ditolak") dan adjustment (T3.5: alasan selalu
 * wajib). Dilempar di dalam transaksi finalisasi sehingga seluruh
 * perubahan (termasuk nomor dokumen yang sudah diambil) ikut rollback.
 *
 * `DomainException` (SPL bawaan PHP), konsisten dengan
 * `DocumentStateException`/`InsufficientStockException`.
 */
final class StockDocumentValidationException extends DomainException {}
