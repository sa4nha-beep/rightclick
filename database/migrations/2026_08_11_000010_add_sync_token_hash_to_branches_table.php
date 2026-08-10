<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token per node (T5.8, CLAUDE.md §8: "diakses melalui VPN dengan
     * token per node"). WireGuard (§14) sudah membatasi lapisan jaringan
     * antar node — token ini adalah lapisan otentikasi APLIKASI di atasnya
     * (pertahanan berlapis, bukan pengganti VPN).
     *
     * `sync_token_hash` menyimpan SHA-256 dari token, BUKAN token itu
     * sendiri (token plaintext hanya ditampilkan SEKALI saat diterbitkan,
     * `php artisan sync:issue-token`) — pola sama Laravel Sanctum: lookup
     * langsung via `WHERE sync_token_hash = ?` (deterministik, cepat),
     * BUKAN `Hash::make()`/`Hash::check()` bcrypt yang butuh iterasi per
     * baris untuk mencari token yang cocok.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('sync_token_hash', 64)->nullable()->unique()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('sync_token_hash');
        });
    }
};
