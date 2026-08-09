<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\AuditAction;
use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\User;
use App\Presentation\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * T1.14 — halaman Audit Log. AC eksplisit: "PT6 — tidak ada tombol hapus
 * di mana pun pada halaman Audit Log".
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    config(['rightclick.node.role' => NodeRole::Hq->value]);

    $this->branch = Branch::factory()->create();
});

it('Owner dapat mengakses halaman Audit Log', function () {
    $owner = User::factory()->for($this->branch, 'defaultBranch')->create();
    $owner->assignRole('owner');

    $this->actingAs($owner)
        ->get('/admin/audit-logs')
        ->assertOk();
});

it('Kasir ditolak mengakses halaman Audit Log (tanpa permission view_audit_logs)', function () {
    $kasir = User::factory()->for($this->branch, 'defaultBranch')->create();
    $kasir->assignRole('kasir');

    $this->actingAs($kasir)
        ->get('/admin/audit-logs')
        ->assertForbidden();
});

it('tidak ada tombol hapus atau aksi massal apa pun pada halaman Audit Log (PT6)', function () {
    $owner = User::factory()->for($this->branch, 'defaultBranch')->create();
    $owner->assignRole('owner');

    AuditLog::create([
        'actor_id' => $owner->id,
        'action' => AuditAction::Created,
        'model_type' => Branch::class,
        'model_id' => $this->branch->id,
        'branch_id' => $this->branch->id,
        'created_at' => now(),
    ]);

    $this->actingAs($owner);

    Livewire::test(ListAuditLogs::class)
        ->assertActionDoesNotExist('delete')
        ->assertActionDoesNotExist('deleteBulk')
        ->assertActionDoesNotExist('forceDelete')
        ->assertActionDoesNotExist('forceDeleteBulk')
        ->assertActionDoesNotExist('edit')
        ->assertActionDoesNotExist('create');
});

it('tidak ada rute create maupun edit terdaftar untuk Audit Log', function () {
    $owner = User::factory()->for($this->branch, 'defaultBranch')->create();
    $owner->assignRole('owner');

    $this->actingAs($owner);

    expect(Route::has('filament.admin.resources.audit-logs.create'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.audit-logs.edit'))->toBeFalse();
});
