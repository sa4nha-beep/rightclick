<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use HasUuidV7;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'pic_name',
        'is_hq',
        'is_active',
        'sync_token_hash',
    ];

    protected $hidden = [
        'sync_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_hq' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'default_branch_id');
    }

    /**
     * @return HasMany<UserBranch>
     */
    public function userBranches(): HasMany
    {
        return $this->hasMany(UserBranch::class);
    }

    /**
     * Terbitkan token sinkronisasi baru (T5.8) — hanya SHA-256-nya yang
     * disimpan (`sync_token_hash`); nilai plaintext yang dikembalikan
     * method ini HANYA ADA SEKALI di memori pemanggil (`php artisan
     * sync:issue-token`) — tidak pernah disimpan, tidak bisa diambil ulang
     * setelah ini, hanya diterbitkan ulang (token lama otomatis tidak
     * berlaku begitu yang baru disimpan).
     */
    public function issueSyncToken(): string
    {
        $token = Str::random(64);
        $this->sync_token_hash = hash('sha256', $token);
        $this->save();

        return $token;
    }

    /**
     * Verifikasi token sinkronisasi (T5.8) — `hash_equals()` untuk
     * perbandingan waktu-konstan (mencegah timing attack), sama alasan
     * Laravel Sanctum membandingkan hash token dengan cara yang sama.
     */
    public function verifySyncToken(string $token): bool
    {
        if ($this->sync_token_hash === null) {
            return false;
        }

        return hash_equals($this->sync_token_hash, hash('sha256', $token));
    }
}
