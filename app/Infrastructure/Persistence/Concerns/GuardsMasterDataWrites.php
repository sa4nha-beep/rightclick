<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Concerns;

use App\Domain\Shared\Enums\NodeRole;

/**
 * Kompensasi lapis aplikasi untuk tabel REPLICATED (CLAUDE.md §7) — hanya
 * node HQ yang boleh menulis master data. RIGHTCLICK tidak memakai RLS
 * (§4), sehingga Policy adalah satu-satunya penjaga di lapisan aplikasi;
 * replikasi read-only di database adalah lapis kedua, bukan pengganti.
 */
trait GuardsMasterDataWrites
{
    protected function nodeCanWriteMasterData(): bool
    {
        return NodeRole::from(config('rightclick.node.role'))->canWriteMasterData();
    }
}
