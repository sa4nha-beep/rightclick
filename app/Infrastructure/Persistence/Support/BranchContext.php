<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Support;

/**
 * Menyimpan cabang aktif untuk permintaan atau job antrean yang sedang
 * berjalan. Dibaca oleh `BranchScope` (HS-ARCH-RIGHTCLICK-v1.1 §4.4).
 *
 * Diikat sebagai *scoped singleton* (lihat `AppServiceProvider::register()`)
 * sehingga Laravel mengosongkannya sendiri di antara permintaan HTTP maupun
 * di antara job queue — worker yang sama tidak boleh membawa cabang dari job
 * sebelumnya ke job berikutnya.
 *
 * Sumber nilainya (sesi pengguna, `default_branch_id` pada `users`, atau
 * pemilihan cabang aktif di antarmuka) adalah tanggung jawab middleware yang
 * dibangun pada T1.4/T2.5 — kelas ini sengaja tidak mengetahui dari mana
 * nilainya berasal.
 */
final class BranchContext
{
    private ?string $branchId = null;

    public function set(?string $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function current(): ?string
    {
        return $this->branchId;
    }

    public function clear(): void
    {
        $this->branchId = null;
    }
}
