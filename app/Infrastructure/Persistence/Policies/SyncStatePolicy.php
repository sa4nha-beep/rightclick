<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\SyncState;
use App\Infrastructure\Persistence\Models\User;

/**
 * `sync_states` — status sinkronisasi per cabang, sisi HQ (T5.8),
 * diperbarui `SyncEventsController`/`SyncHealthController`. Sama pola
 * `OutboxEventPolicy`: create/update/delete/restore/forceDelete SELALU
 * `false` dari sisi pengguna, viewAny/view digerbang `manage_settings`.
 */
class SyncStatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_settings');
    }

    public function view(User $user, SyncState $syncState): bool
    {
        return $user->can('manage_settings');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SyncState $syncState): bool
    {
        return false;
    }

    public function delete(User $user, SyncState $syncState): bool
    {
        return false;
    }

    public function restore(User $user, SyncState $syncState): bool
    {
        return false;
    }

    public function forceDelete(User $user, SyncState $syncState): bool
    {
        return false;
    }
}
