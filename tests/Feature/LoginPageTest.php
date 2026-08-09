<?php

declare(strict_types=1);

use App\Application\Services\NodeConnectivityService;
use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\User;
use App\Presentation\Filament\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * T1.11 — HS-UI-RIGHTCLICK-v1.1 §8.2/§8.3, AC: "Tiga keadaan tampil benar:
 * normal, terputus, kredensial salah".
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $branch = Branch::factory()->create();

    $this->user = User::factory()->for($branch, 'defaultBranch')->create([
        'username' => 'kasir01',
        'password' => Hash::make('rahasia-benar'),
    ]);
});

it('memakai field username, bukan email', function () {
    Livewire::test(Login::class)
        ->assertFormFieldExists('username')
        ->assertSchemaComponentDoesNotExist('email');
});

it('keadaan normal — berhasil login dengan username dan kata sandi benar', function () {
    Livewire::test(Login::class)
        ->fillForm([
            'username' => 'kasir01',
            'password' => 'rahasia-benar',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->user);
});

it('keadaan kredensial salah — password keliru menolak login dengan pesan galat', function () {
    Livewire::test(Login::class)
        ->fillForm([
            'username' => 'kasir01',
            'password' => 'salah-total',
        ])
        ->call('authenticate')
        ->assertHasFormErrors();

    $this->assertGuest();
});

it('keadaan kredensial salah — username tidak dikenal menolak login', function () {
    Livewire::test(Login::class)
        ->fillForm([
            'username' => 'tidak-ada',
            'password' => 'apapun',
        ])
        ->call('authenticate')
        ->assertHasFormErrors();

    $this->assertGuest();
});

it('keadaan normal — banner terputus tersembunyi saat node HQ', function () {
    config(['rightclick.node.role' => NodeRole::Hq->value]);

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('Terputus dari pusat');
});

it('keadaan terputus — banner tampil saat node cabang tanpa replikasi aktif', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Terputus dari pusat')
        ->assertSee('Transaksi tetap tersimpan secara lokal');
});

it('keadaan normal — banner tersembunyi saat node cabang terkonfirmasi tersambung', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);

    // NodeConnectivityService sengaja `final` (lihat dokblok kelasnya) —
    // dipalsukan lewat DB::select, bukan Mockery::mock() atas kelasnya
    // sendiri (Mockery menolak mock partial kelas final tanpa instance
    // nyata), sama seperti pola di NodeConnectivityServiceTest.
    DB::shouldReceive('select')
        ->once()
        ->with('SELECT latest_end_time FROM pg_stat_subscription')
        ->andReturn([
            (object) ['latest_end_time' => now()->subMinute()->toDateTimeString()],
        ]);

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('Terputus dari pusat');
});
