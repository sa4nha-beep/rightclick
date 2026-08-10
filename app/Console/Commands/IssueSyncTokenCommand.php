<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Models\Branch;
use Illuminate\Console\Command;

/**
 * Menerbitkan token sinkronisasi (T5.8, CLAUDE.md §8: "token per node")
 * untuk satu cabang. Token PLAINTEXT hanya ditampilkan SEKALI di sini —
 * `branches.sync_token_hash` hanya menyimpan SHA-256-nya (lihat docblock
 * migration/`Branch::issueSyncToken()`), tidak bisa diambil ulang setelah
 * ini. Menerbitkan ulang token untuk cabang yang sama OTOMATIS membuat
 * token lama tidak berlaku lagi.
 */
class IssueSyncTokenCommand extends Command
{
    protected $signature = 'sync:issue-token {branch_code : Kode cabang, mis. HK-A}';

    protected $description = 'Menerbitkan token sinkronisasi baru untuk satu cabang (T5.8)';

    public function handle(): int
    {
        $branch = Branch::query()->where('code', $this->argument('branch_code'))->first();

        if ($branch === null) {
            $this->error("Cabang dengan kode '{$this->argument('branch_code')}' tidak ditemukan.");

            return self::FAILURE;
        }

        $token = $branch->issueSyncToken();

        $this->warn('Token HANYA ditampilkan sekali — simpan sekarang, tidak dapat diambil ulang:');
        $this->line($token);
        $this->info("Token sinkronisasi untuk cabang {$branch->code} berhasil diterbitkan. Token lama (bila ada) sudah tidak berlaku.");

        return self::SUCCESS;
    }
}
