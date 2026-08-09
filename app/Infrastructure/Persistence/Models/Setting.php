<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Concerns\Auditable;
use App\Infrastructure\Persistence\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan sistem key-value (T1.9). Nilai ambang TH1–TH5c
 * (HS-PRD-RIGHTCLICK-v1.0 §5.1) hidup di sini, diisi `SettingSeeder` dan
 * dapat dikoreksi lewat panel tanpa deploy ulang (§5.2 TA10).
 *
 * REPLICATED (CLAUDE.md §7) — global per `key`, bukan branch-scoped (tidak
 * ada `branch_id`); `SettingPolicy` menegakkan HQ sebagai satu-satunya
 * penulis.
 *
 * `Auditable` (T1.6): perubahan ambang WAJIB tercatat di audit log (TA10 —
 * "perubahan ambang memerlukan setting.manage dan tercatat di audit log").
 *
 * @property int|float|bool|string|null $value Cast 'array' menyimpan
 *                                             skalar JSON apa pun (T1.14 — Larastan tanpa anotasi ini menyimpulkan
 *                                             `string` dari cast 'array' secara keliru, menandai `is_bool($v)` di
 *                                             SettingForm sebagai "selalu false").
 */
class Setting extends Model
{
    use Auditable;
    use HasUuidV7;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Baca satu nilai ambang. `$default` dipakai bila kunci belum diseed —
     * mis. lingkungan pengujian yang membuat baris `settings` sendiri.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting === null ? $default : $setting->value;
    }
}
