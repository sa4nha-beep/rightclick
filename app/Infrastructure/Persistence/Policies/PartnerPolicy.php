<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Concerns\GuardsMasterDataWrites;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\User;

/**
 * `partners` adalah tabel REPLICATED (CLAUDE.md §7) — HQ satu-satunya
 * penulis, node cabang membaca replika read-only. Setiap write ability
 * di bawah menolak node cabang sebagai kompensasi lapis aplikasi (M02),
 * sama seperti `BranchPolicy`.
 *
 * `manage_partner_prices` dan `manage_partner_limits` (PermissionSeeder,
 * T1.5) belum digunakan di sini — keduanya reserved untuk gating field
 * lebih halus (mis. `credit_limit`) di lapisan Filament Resource (T2.9),
 * bukan ability Policy standar CRUD.
 */
class PartnerPolicy
{
    use GuardsMasterDataWrites;

    public function viewAny(User $user): bool
    {
        return $user->can('view_partners');
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->can('view_partners');
    }

    public function create(User $user): bool
    {
        return $user->can('create_partners') && $this->nodeCanWriteMasterData();
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->can('edit_partners') && $this->nodeCanWriteMasterData();
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->can('delete_partners') && $this->nodeCanWriteMasterData();
    }

    public function restore(User $user, Partner $partner): bool
    {
        return $user->can('delete_partners') && $this->nodeCanWriteMasterData();
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, Partner $partner): bool
    {
        return false;
    }
}
