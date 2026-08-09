<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\NodeConnectivityService;
use App\Domain\Shared\Enums\NodeRole;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T1.11 — U8/§8.2 HS-UI-RIGHTCLICK-v1.1: deteksi konektivitas ke HQ.
 */
class NodeConnectivityServiceTest extends TestCase
{
    protected NodeConnectivityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new NodeConnectivityService;
    }

    #[Test]
    public function it_always_reports_hq_node_as_connected(): void
    {
        config(['rightclick.node.role' => NodeRole::Hq->value]);

        $this->assertTrue($this->service->isConnectedToHq());
    }

    #[Test]
    public function it_reports_branch_disconnected_when_no_subscription_exists(): void
    {
        config(['rightclick.node.role' => NodeRole::Branch->value]);

        // Database pengujian tidak memiliki subscription replikasi logikal
        // sungguhan — pg_stat_subscription kosong secara alami, tanpa perlu
        // mock. Ini adalah keadaan default yang jujur untuk node yang belum
        // direplikasi dari mana pun.
        $this->assertFalse($this->service->isConnectedToHq());
    }

    #[Test]
    public function it_reports_branch_connected_when_subscription_activity_is_recent(): void
    {
        config(['rightclick.node.role' => NodeRole::Branch->value]);

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT latest_end_time FROM pg_stat_subscription')
            ->andReturn([
                (object) ['latest_end_time' => now()->subMinute()->toDateTimeString()],
            ]);

        $this->assertTrue($this->service->isConnectedToHq());
    }

    #[Test]
    public function it_reports_branch_disconnected_when_subscription_activity_is_stale(): void
    {
        config(['rightclick.node.role' => NodeRole::Branch->value]);

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT latest_end_time FROM pg_stat_subscription')
            ->andReturn([
                (object) ['latest_end_time' => now()->subMinutes(30)->toDateTimeString()],
            ]);

        $this->assertFalse($this->service->isConnectedToHq());
    }

    #[Test]
    public function it_reports_branch_disconnected_when_subscription_query_fails(): void
    {
        config(['rightclick.node.role' => NodeRole::Branch->value]);

        DB::shouldReceive('select')
            ->once()
            ->andThrow(new \RuntimeException('connection refused'));

        // Kegagalan query TIDAK BOLEH melempar exception — halaman login
        // harus tetap tampil dengan banner yang jujur, bukan error 500.
        $this->assertFalse($this->service->isConnectedToHq());
    }
}
