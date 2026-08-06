<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Concerns;

use App\Application\Services\AuditService;
use App\Domain\Shared\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Observer otomatis untuk audit log (T1.6, R11).
 *
 * Model transaksi cukup `use Auditable;` — created/updated/deleted/restored
 * tercatat otomatis ke `audit_logs` tanpa panggilan manual tersebar di
 * business logic (yang mudah lupa dipanggil di satu jalur tapi tidak di
 * jalur lain).
 *
 * Tanpa aktor terautentikasi (Auth::check() === false), logging DILEWATI —
 * bukan dicatat dengan actor_id kosong. Ini menyamai perlakuan
 * TracksUserActions terhadap created_by/updated_by: operasi bootstrap
 * (seeder, migration) bukan "aksi sensitif oleh pengguna" dalam pengertian
 * R11, dan beberapa model (mis. Setting) tidak punya branch_id sendiri —
 * tanpa pengguna terautentikasi, AuditService tidak punya cara menentukan
 * branch_id (kolom NOT NULL di audit_logs), sehingga insert akan gagal.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            if (! Auth::check()) {
                return;
            }

            app(AuditService::class)->log(
                $model,
                AuditAction::Created,
                newValues: $model->getAttributes(),
            );
        });

        static::updated(function (Model $model) {
            if (! Auth::check()) {
                return;
            }

            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            app(AuditService::class)->log(
                $model,
                AuditAction::Updated,
                oldValues: $original,
                newValues: $changes,
            );
        });

        static::deleted(function (Model $model) {
            if (! Auth::check()) {
                return;
            }

            $action = method_exists($model, 'trashed') && $model->trashed()
                ? AuditAction::SoftDeleted
                : AuditAction::Deleted;

            app(AuditService::class)->log(
                $model,
                $action,
                oldValues: $model->getAttributes(),
            );
        });

        // Static helper `restored()` hanya terdaftar oleh trait SoftDeletes —
        // model tanpa soft delete (mis. Approval) tidak memilikinya, dan
        // Larastan menandainya sebagai method tak dikenal pada kelas
        // tersebut walau dibungkus pengecekan runtime. `registerModelEvent()`
        // adalah method generik yang selalu ada di setiap model Eloquent
        // (didefinisikan `HasEvents`, dipakai `restored()` di baliknya) —
        // aman dipanggil tanpa syarat, tidak pernah terpicu pada model yang
        // memang tidak soft-deletable.
        static::registerModelEvent('restored', function (Model $model) {
            if (! Auth::check()) {
                return;
            }

            app(AuditService::class)->log(
                $model,
                AuditAction::Restored,
                newValues: $model->getAttributes(),
            );
        });
    }
}
