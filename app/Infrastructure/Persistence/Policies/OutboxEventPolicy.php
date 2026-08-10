<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\OutboxEvent;
use App\Infrastructure\Persistence\Models\User;

/**
 * `outbox_events` — plumbing sinkronisasi internal (T5.7), satu-satunya
 * jalur tulis adalah `OutboxService`. Sama pola `StockMutationPolicy`/
 * `CashEntryPolicy`: create/update/delete/restore/forceDelete SELALU
 * `false`.
 *
 * `viewAny`/`view` digerbang `manage_settings` (BUKAN permission baru) —
 * tidak ada kolom "hutang/piutang/kas" yang cocok di matriks §10 karena
 * ini murni kekhawatiran teknis/operasional sinkronisasi, bukan data
 * bisnis; `manage_settings` sudah dipakai untuk kepentingan
 * teknis/administratif setara (`SettingPolicy`), Owner/HQ saja. Belum ada
 * Filament Resource yang membaca ini di T5.7 — monitoring outbox
 * (mis. panel admin sinkronisasi) adalah pekerjaan T5.8.
 */
class OutboxEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_settings');
    }

    public function view(User $user, OutboxEvent $outboxEvent): bool
    {
        return $user->can('manage_settings');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, OutboxEvent $outboxEvent): bool
    {
        return false;
    }

    public function delete(User $user, OutboxEvent $outboxEvent): bool
    {
        return false;
    }

    public function restore(User $user, OutboxEvent $outboxEvent): bool
    {
        return false;
    }

    public function forceDelete(User $user, OutboxEvent $outboxEvent): bool
    {
        return false;
    }
}
