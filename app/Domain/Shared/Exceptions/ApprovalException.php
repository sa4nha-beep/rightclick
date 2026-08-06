<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use DomainException;

/**
 * Pelanggaran aturan alur approval (AP-01/AP-04) — memutuskan permintaan
 * yang sudah diputuskan, atau menolak tanpa alasan.
 *
 * `DomainException` (SPL bawaan PHP, bukan Illuminate) — pelanggaran aturan
 * bisnis, bukan kegagalan infrastruktur.
 */
final class ApprovalException extends DomainException {}
