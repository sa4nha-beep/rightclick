<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\AuditAction;
use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Setting;
use App\Infrastructure\Persistence\Models\User;
use App\Presentation\Filament\Resources\Settings\Pages\EditSetting;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * T1.14 — halaman Pengaturan (TA10). `manage_settings` hanya Owner, dan
 * hanya di node HQ (SettingPolicy, REPLICATED).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    config(['rightclick.node.role' => NodeRole::Hq->value]);

    $this->branch = Branch::factory()->create();
});

it('Owner dapat mengakses halaman Pengaturan di node HQ', function () {
    $owner = User::factory()->for($this->branch, 'defaultBranch')->create();
    $owner->assignRole('owner');

    $this->actingAs($owner)
        ->get('/admin/settings')
        ->assertOk();
});

it('Admin ditolak mengakses halaman Pengaturan (manage_settings hanya Owner)', function () {
    $admin = User::factory()->for($this->branch, 'defaultBranch')->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertForbidden();
});

it('Owner ditolak mengubah pengaturan saat node adalah cabang (REPLICATED)', function () {
    $owner = User::factory()->for($this->branch, 'defaultBranch')->create();
    $owner->assignRole('owner');
    $setting = Setting::create(['key' => 'test.threshold', 'value' => 100, 'description' => 'Uji']);

    config(['rightclick.node.role' => NodeRole::Branch->value]);

    $this->actingAs($owner)
        ->get("/admin/settings/{$setting->id}/edit")
        ->assertForbidden();
});

it('Owner dapat mengubah nilai numerik pengaturan', function () {
    $owner = User::factory()->for($this->branch, 'defaultBranch')->create();
    $owner->assignRole('owner');
    $setting = Setting::create([
        'key' => 'discount.max_kasir',
        'value' => 100000,
        'description' => 'TH1',
    ]);

    $this->actingAs($owner);

    Livewire::test(EditSetting::class, ['record' => $setting->id])
        ->fillForm(['value' => 150000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($setting->fresh()->value)->toBe(150000);
});

it('Owner dapat mengubah nilai boolean pengaturan lewat toggle', function () {
    $owner = User::factory()->for($this->branch, 'defaultBranch')->create();
    $owner->assignRole('owner');
    $setting = Setting::create([
        'key' => 'price.block_below_cost',
        'value' => true,
        'description' => 'TH5c',
    ]);

    $this->actingAs($owner);

    Livewire::test(EditSetting::class, ['record' => $setting->id])
        ->fillForm(['value' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($setting->fresh()->value)->toBeFalse();
});

it('perubahan pengaturan tercatat di audit log (TA10)', function () {
    $owner = User::factory()->for($this->branch, 'defaultBranch')->create();
    $owner->assignRole('owner');
    $setting = Setting::create([
        'key' => 'discount.max_admin',
        'value' => 300000,
        'description' => 'TH2',
    ]);

    $this->actingAs($owner);

    Livewire::test(EditSetting::class, ['record' => $setting->id])
        ->fillForm(['value' => 350000])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = AuditLog::query()
        ->where('model_type', Setting::class)
        ->where('model_id', $setting->id)
        ->where('action', AuditAction::Updated->value)
        ->first();

    expect($log)->not->toBeNull()
        ->and((int) $log->new_values['value'])->toBe(350000);
});
