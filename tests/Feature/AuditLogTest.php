<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\AuditService;
use App\Domain\Shared\Enums\AuditAction;
use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;

    protected User $actor;

    protected User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditService = app(AuditService::class);
        $branch = Branch::factory()->create();
        $this->actor = User::factory()->for($branch, 'defaultBranch')->create();
        $this->targetUser = User::factory()->for($branch, 'defaultBranch')->create();
    }

    #[Test]
    public function it_logs_access_denied_action(): void
    {
        $this->actingAs($this->actor);

        $metadata = [
            'permission' => 'delete_users',
            'reason' => 'Kasir tidak berwenang menghapus pengguna',
        ];

        $log = $this->auditService->logAccessDenied(
            User::class,
            $this->targetUser->id,
            metadata: $metadata,
            branchId: $this->actor->default_branch_id,
        );

        $this->assertEquals(AuditAction::AccessDenied, $log->action);
        $this->assertEquals(User::class, $log->model_type);
        $this->assertEquals($metadata, $log->metadata);
    }

    #[Test]
    public function it_creates_audit_log_with_created_at(): void
    {
        $this->actingAs($this->actor);

        $log = $this->auditService->log(
            $this->targetUser,
            AuditAction::Updated,
            oldValues: ['name' => 'Old Name'],
            newValues: ['name' => 'New Name'],
        );

        // Verify created_at is recorded (append-only logs should have creation timestamp)
        $this->assertNotNull($log->created_at);
        $this->assertIsNumeric($log->created_at->timestamp);
    }

    #[Test]
    public function it_stores_full_audit_context(): void
    {
        $this->actingAs($this->actor);

        $log = $this->auditService->log(
            $this->targetUser,
            AuditAction::Updated,
            oldValues: ['email' => 'old@example.com'],
            newValues: ['email' => 'new@example.com'],
            metadata: ['reason' => 'User requested change'],
        );

        $this->assertNotNull($log->id);
        $this->assertEquals($this->actor->id, $log->actor_id);
        $this->assertEquals(AuditAction::Updated, $log->action);
        $this->assertEquals(User::class, $log->model_type);
        $this->assertEquals($this->targetUser->id, $log->model_id);
        $this->assertEquals(['email' => 'old@example.com'], $log->old_values);
        $this->assertEquals(['email' => 'new@example.com'], $log->new_values);
        $this->assertEquals(['reason' => 'User requested change'], $log->metadata);
    }

    #[Test]
    public function it_records_different_action_types(): void
    {
        $this->actingAs($this->actor);

        $logCreate = $this->auditService->log($this->targetUser, AuditAction::Created);
        $logUpdate = $this->auditService->log($this->targetUser, AuditAction::Updated);
        $logDelete = $this->auditService->log($this->targetUser, AuditAction::Deleted);
        $logAccessDenied = $this->auditService->logAccessDenied(
            User::class,
            $this->targetUser->id,
            branchId: $this->actor->default_branch_id,
        );

        $this->assertEquals(AuditAction::Created, $logCreate->action);
        $this->assertEquals(AuditAction::Updated, $logUpdate->action);
        $this->assertEquals(AuditAction::Deleted, $logDelete->action);
        $this->assertEquals(AuditAction::AccessDenied, $logAccessDenied->action);
    }

    #[Test]
    public function it_can_query_audit_logs_by_actor(): void
    {
        $otherActor = User::factory()->for(Branch::find($this->actor->default_branch_id), 'defaultBranch')->create();

        $this->auditService->log($this->targetUser, AuditAction::Created, actorId: $this->actor->id);
        $this->auditService->log($this->targetUser, AuditAction::Updated, actorId: $otherActor->id);

        $logsFromActor = AuditLog::query()->where('actor_id', $this->actor->id)->get();
        $logsFromOther = AuditLog::query()->where('actor_id', $otherActor->id)->get();

        $this->assertCount(1, $logsFromActor);
        $this->assertCount(1, $logsFromOther);
    }
}
