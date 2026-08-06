<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\User;

/**
 * `approvals` (T1.9). Tiga golongan pemakai (PRD §12.2 AP-02/AP-03):
 *
 *  - Pemohon (`requested_by`) melihat status permintaannya sendiri.
 *  - Penyetuju (`decide_approval`) melihat dan memutuskan seluruh
 *    permintaan tertunda di cabangnya (disaring `BranchScope`, bukan
 *    Policy ini).
 *  - Peran tanpa `request_approval` maupun `decide_approval` (mis. viewer)
 *    tidak melihat apa pun.
 *
 * Tidak ada `update()`/`delete()` — keputusan hanya berubah lewat aksi
 * `decide()` (dipanggil `App\Application\Services\ApprovalService`), dan
 * permintaan yang sudah diputuskan adalah jejak permanen (lihat catatan
 * pada model `Approval`).
 */
class ApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('request_approval') || $user->can('decide_approval');
    }

    public function view(User $user, Approval $approval): bool
    {
        // Perbandingan string eksplisit: primary key RIGHTCLICK adalah UUID
        // v7 (HasUuidV7), tetapi Larastan menyimpulkan tipe kolom FK generik
        // sebagai int kecuali dipaksa string di kedua sisi.
        return (string) $approval->requested_by === (string) $user->id || $user->can('decide_approval');
    }

    /**
     * Dipakai `ApprovalService::request()` sebagai gate lapis Policy bahwa
     * peran pemohon memang berwenang memicu alur approval sama sekali.
     */
    public function create(User $user): bool
    {
        return $user->can('request_approval');
    }

    /**
     * Menyetujui atau menolak — `ApprovalService::approve()`/`reject()`.
     */
    public function decide(User $user, Approval $approval): bool
    {
        return $user->can('decide_approval');
    }

    public function update(User $user, Approval $approval): bool
    {
        return false;
    }

    public function delete(User $user, Approval $approval): bool
    {
        return false;
    }
}
