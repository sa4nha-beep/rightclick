<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Jenis aksi yang dicatat di audit log (R11).
 *
 * Setiap nilai adalah string yang akan disimpan di kolom `action` tabel
 * `audit_logs`, dikembalikan sebagai enum untuk tipe-safety dalam kode
 * (Eloquent attribute casting).
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case SoftDeleted = 'soft_deleted';
    case Restored = 'restored';
    case Finalized = 'finalized';
    case Voided = 'voided';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case AccessDenied = 'access_denied';
}
