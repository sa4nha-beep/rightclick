<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\ApprovalService;
use App\Domain\Shared\Enums\AuditAction;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Setting;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T1.6 — observer otomatis `Auditable` diterapkan pada Setting dan Approval.
 * Cakupan: perubahan tercatat otomatis saat ada aktor, dilewati (bukan
 * gagal) tanpa aktor — seeder/bootstrap tidak boleh crash karena audit.
 */
class AuditableTraitTest extends TestCase
{
    use RefreshDatabase;

    protected User $actor;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['is_hq' => true]);
        $this->actor = User::factory()->for($this->branch, 'defaultBranch')->create();
    }

    #[Test]
    public function it_logs_setting_update_when_authenticated(): void
    {
        $this->actingAs($this->actor);

        $setting = Setting::create([
            'key' => 'test.threshold',
            'value' => 100,
            'description' => 'Test',
        ]);

        // Log 'created' should exist
        $createdLog = AuditLog::query()
            ->where('model_type', Setting::class)
            ->where('model_id', $setting->id)
            ->where('action', AuditAction::Created->value)
            ->first();
        $this->assertNotNull($createdLog);

        $setting->update(['value' => 200]);

        $updatedLog = AuditLog::query()
            ->where('model_type', Setting::class)
            ->where('model_id', $setting->id)
            ->where('action', AuditAction::Updated->value)
            ->first();

        $this->assertNotNull($updatedLog);
        $this->assertEquals(100, $updatedLog->old_values['value']);
        $this->assertEquals(200, $updatedLog->new_values['value']);
    }

    #[Test]
    public function it_skips_logging_without_authenticated_actor(): void
    {
        // No actingAs — simulates seeder/console context
        $setting = Setting::create([
            'key' => 'test.no_auth',
            'value' => 42,
            'description' => 'Test',
        ]);

        $this->assertEquals(0, AuditLog::query()->count());

        $setting->update(['value' => 99]);

        $this->assertEquals(0, AuditLog::query()->count());
    }

    #[Test]
    public function it_does_not_log_when_update_has_no_meaningful_changes(): void
    {
        $this->actingAs($this->actor);

        $setting = Setting::create([
            'key' => 'test.unchanged',
            'value' => 1,
            'description' => 'Test',
        ]);

        AuditLog::query()->delete();

        // Save without changing any watched attribute (only touches updated_at)
        $setting->touch();

        $this->assertEquals(0, AuditLog::query()->count());
    }

    #[Test]
    public function it_logs_approval_request_and_decision_automatically(): void
    {
        $this->actingAs($this->actor);

        $approvalService = app(ApprovalService::class);
        $approval = $approvalService->request($this->branch);

        $createdLog = AuditLog::query()
            ->where('model_type', Approval::class)
            ->where('model_id', $approval->id)
            ->where('action', AuditAction::Created->value)
            ->first();
        $this->assertNotNull($createdLog);

        $approvalService->approve($approval);

        $updatedLog = AuditLog::query()
            ->where('model_type', Approval::class)
            ->where('model_id', $approval->id)
            ->where('action', AuditAction::Updated->value)
            ->first();

        $this->assertNotNull($updatedLog);
        $this->assertEquals('pending', $updatedLog->old_values['status']);
        $this->assertEquals('approved', $updatedLog->new_values['status']);
    }
}
