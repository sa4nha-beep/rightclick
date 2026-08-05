<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model Eloquent berada di lapisan Infrastructure/Persistence
 * (HS-ARCH-RIGHTCLICK-v1.1 bagian 2.1) — bukan di `app/Models`.
 *
 * Bentuk final tabel `users` (UUID v7, `user_branches`, penonaktifan darurat
 * lokal) adalah lingkup T1.4. Di T1.1 model ini hanya dipindahkan ke lapisan
 * yang benar agar tidak ada kode yang lahir di lokasi yang salah.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * S4 — kata sandi tidak pernah ikut terserialisasi.
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
