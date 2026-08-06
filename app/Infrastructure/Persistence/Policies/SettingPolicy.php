<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Concerns\GuardsMasterDataWrites;
use App\Infrastructure\Persistence\Models\Setting;
use App\Infrastructure\Persistence\Models\User;

/**
 * `settings` adalah tabel REPLICATED (CLAUDE.md §7) — HQ satu-satunya
 * penulis, node cabang membaca replika read-only. Satu permission
 * (`manage_settings`) menjaga seluruh aksi tulis — dokumen tidak
 * membedakan izin lihat dari izin ubah untuk pengaturan sistem (PRD §5.1
 * TA10: perubahan ambang memerlukan `setting.manage`).
 */
class SettingPolicy
{
    use GuardsMasterDataWrites;

    public function viewAny(User $user): bool
    {
        return $user->can('manage_settings');
    }

    public function view(User $user, Setting $setting): bool
    {
        return $user->can('manage_settings');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_settings') && $this->nodeCanWriteMasterData();
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->can('manage_settings') && $this->nodeCanWriteMasterData();
    }

    public function delete(User $user, Setting $setting): bool
    {
        return $user->can('manage_settings') && $this->nodeCanWriteMasterData();
    }
}
