<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Jalur normal alur approval (PRD §12.2 AP-01–AP-04). Modul yang menghadapi
 * ambang TH1–TH5c (mis. diskon POS T4.8, penyesuaian stok T3.6, perubahan
 * harga T2.7, PO T5.1) memanggil `request()` saat ambang terlampaui —
 * transaksi itu sendiri TIDAK ditolak (AP-01), hanya menunggu keputusan di
 * sini sebagai dokumen `Approval` terpisah.
 *
 * Kolom ambang mana yang terlampaui dan nilainya (AP-05, mis. "Diskon
 * Rp150.000 melebihi batas Kasir Rp100.000") dihitung ulang oleh modul
 * pemanggil dari data `$approvable` itu sendiri beserta `Setting` terkait —
 * bukan disimpan sebagai teks bebas di sini, karena DB Design §4.1 hanya
 * memberi `approvals` satu kolom `reason`, dan itu dipakai untuk alasan
 * PENOLAKAN (AP-04), bukan konteks permintaan.
 */
final class ApprovalService
{
    /**
     * Ajukan permintaan approval untuk sebuah dokumen. `$approvable` boleh
     * berupa model apa pun — layanan ini hanya membaca kelas morph dan
     * primary key-nya, tidak menyentuh kolom spesifik dokumennya.
     *
     * `$branchId` eksplisit dibutuhkan karena middleware yang mengisi
     * `BranchContext` (dipakai `BelongsToBranch::bootBelongsToBranch()`
     * untuk auto-fill `branch_id` saat `creating`) baru dibangun T2.5 —
     * tanpa ini, `Approval::create()` gagal kolom NOT NULL `branch_id`.
     * Default ke cabang aktif pengguna login bila tidak diberikan eksplisit.
     */
    public function request(Model $approvable, ?string $requestedBy = null, ?string $branchId = null): Approval
    {
        return Approval::create([
            'branch_id' => $branchId ?? Auth::user()?->default_branch_id,
            'approvable_type' => $approvable->getMorphClass(),
            'approvable_id' => $approvable->getKey(),
            'requested_by' => $requestedBy ?? Auth::id(),
            'status' => ApprovalStatus::Pending,
            'requested_at' => now(),
        ]);
    }

    public function approve(Approval $approval, ?string $approverId = null): void
    {
        $this->assertPending($approval);

        $approval->approver_id = $approverId ?? Auth::id();
        $approval->setAttribute('status', ApprovalStatus::Approved);
        $approval->setAttribute('decided_at', now());
        $approval->save();
    }

    /**
     * Tolak permintaan. Alasan wajib diisi (AP-04).
     */
    public function reject(Approval $approval, string $reason, ?string $approverId = null): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new ApprovalException('Penolakan wajib menyertakan alasan (AP-04).');
        }

        $this->assertPending($approval);

        $approval->approver_id = $approverId ?? Auth::id();
        $approval->setAttribute('status', ApprovalStatus::Rejected);
        $approval->setAttribute('decided_at', now());
        $approval->reason = $reason;
        $approval->save();
    }

    private function assertPending(Approval $approval): void
    {
        $status = $approval->getAttribute('status');
        $status = $status instanceof ApprovalStatus ? $status : ApprovalStatus::from($status);

        if ($status !== ApprovalStatus::Pending) {
            throw new ApprovalException('Permintaan approval ini sudah diputuskan.');
        }
    }
}
