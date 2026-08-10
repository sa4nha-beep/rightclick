<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exceptions;

use DomainException;

/**
 * Pelanggaran aturan shift kasir (T4.1) — mis. menutup shift yang sudah
 * ditutup, atau kas fisik negatif. `DomainException`, konsisten dengan
 * `SaleValidationException`.
 */
final class CashierShiftException extends DomainException {}
