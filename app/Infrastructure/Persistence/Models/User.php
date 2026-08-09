<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Database\Factories\Infrastructure\Persistence\Models\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuidV7;
    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'default_branch_id',
        'is_active',
        'locally_disabled_at',
    ];

    /**
     * S4 — kata sandi dan remember_token tidak pernah muncul di log atau
     * terserialisasi.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'locally_disabled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch>
     */
    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    /**
     * @return HasMany<UserBranch>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(UserBranch::class);
    }

    /**
     * `Filament\Http\Middleware\Authenticate` menolak SELURUH akses panel
     * di lingkungan non-`local` bagi model yang tidak implement
     * `FilamentUser` — celah yang baru terlihat setelah `APP_ENV=testing`
     * benar-benar aktif (T1.11). Sekaligus tempat wajar menegakkan PT10
     * (HS-PERM-RIGHTCLICK-v1.2 §7): akun nonaktif — baik dari HQ
     * (`is_active=false`) maupun penonaktifan darurat di cabang
     * (`locally_disabled_at` terisi, `UserPolicy::manageEmergencyDisable()`)
     * — ditolak pada setiap permintaan panel berikutnya.
     *
     * Efek samping logout+invalidate sesi dilakukan DI SINI, bukan lewat
     * middleware terpisah — `Filament\Http\Middleware\Authenticate`
     * memanggil method ini pada jalur permintaan Livewire full-page yang
     * tidak selalu melewati seluruh array `->authMiddleware()` secara
     * berurutan seperti permintaan HTTP biasa (middleware kustom yang
     * ditaruh sebelum `Authenticate::class` di array itu terbukti tidak
     * konsisten terpanggil pada percobaan). `canAccessPanel()` sendiri
     * SELALU dipanggil Filament tepat sebelum keputusan izin — titik yang
     * andal untuk memastikan sesi akun nonaktif benar-benar berakhir, bukan
     * cuma diblokir berulang di gerbang panel tanpa sesi lama pernah mati.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->is_active && $this->locally_disabled_at === null) {
            return true;
        }

        if (Auth::hasUser() && Auth::id() === $this->getKey() && request()->hasSession()) {
            Auth::guard('web')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return false;
    }
}
