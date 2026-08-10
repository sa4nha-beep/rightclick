<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\ProcessedEvent;
use App\Infrastructure\Persistence\Models\User;

/**
 * `processed_events` — ledger idempotensi sisi HQ (T5.8), satu-satunya
 * jalur tulis adalah `SyncEventProcessor`. Sama pola `OutboxEventPolicy`:
 * create/update/delete/restore/forceDelete SELALU `false`, viewAny/view
 * digerbang `manage_settings` (kekhawatiran teknis/operasional, bukan
 * data bisnis).
 */
class ProcessedEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_settings');
    }

    public function view(User $user, ProcessedEvent $processedEvent): bool
    {
        return $user->can('manage_settings');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProcessedEvent $processedEvent): bool
    {
        return false;
    }

    public function delete(User $user, ProcessedEvent $processedEvent): bool
    {
        return false;
    }

    public function restore(User $user, ProcessedEvent $processedEvent): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProcessedEvent $processedEvent): bool
    {
        return false;
    }
}
