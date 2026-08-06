<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use DomainException;

/**
 * Pelanggaran aturan siklus hidup dokumen (R4, AC-02) — dokumen final yang
 * coba diedit, void tanpa alasan, atau perubahan pada dokumen yang sudah void.
 *
 * `DomainException` (SPL bawaan PHP, bukan Illuminate) dipilih karena ini
 * murni pelanggaran aturan bisnis, bukan kegagalan infrastruktur — konsisten
 * dengan lapisan Domain yang tidak boleh bergantung pada framework.
 */
final class DocumentStateException extends DomainException {}
