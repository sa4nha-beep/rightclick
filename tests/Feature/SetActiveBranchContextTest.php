<?php

declare(strict_types=1);

use App\Http\Middleware\SetActiveBranchContext;
use App\Infrastructure\Persistence\Models\User;
use App\Infrastructure\Persistence\Models\UserBranch;
use App\Infrastructure\Persistence\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    Auth::logout();
    app(BranchContext::class)->clear();
    DB::rollBack();
});

/**
 * Request dengan session array yang benar-benar "started" — meniru apa
 * yang `StartSession` middleware lakukan pada permintaan HTTP nyata,
 * tanpa perlu menjalankan seluruh kernel HTTP hanya untuk menguji satu
 * middleware.
 */
function requestWithSession(array $sessionData = []): Request
{
    $session = new Store('test-session', new ArraySessionHandler(120));
    $session->start();

    foreach ($sessionData as $key => $value) {
        $session->put($key, $value);
    }

    $request = Request::create('/admin');
    $request->setLaravelSession($session);

    return $request;
}

function runMiddleware(Request $request): void
{
    (new SetActiveBranchContext)->handle($request, fn ($req) => response('ok'));
}

it('mengisi BranchContext dari default_branch_id saat tidak ada sesi cabang aktif', function () {
    $branch = makeTestBranch();
    $user = User::factory()->create(['default_branch_id' => $branch->id]);
    Auth::login($user);

    runMiddleware(requestWithSession());

    expect(app(BranchContext::class)->current())->toBe($branch->id);
});

it('mengisi BranchContext dari sesi active_branch_id bila cabang ditugaskan ke pengguna', function () {
    $defaultBranch = makeTestBranch();
    $otherBranch = makeTestBranch();
    $user = User::factory()->create(['default_branch_id' => $defaultBranch->id]);
    UserBranch::create(['user_id' => $user->id, 'branch_id' => $otherBranch->id]);
    Auth::login($user);

    runMiddleware(requestWithSession(['active_branch_id' => $otherBranch->id]));

    expect(app(BranchContext::class)->current())->toBe($otherBranch->id);
});

it('mengabaikan sesi active_branch_id yang bukan cabang milik pengguna dan jatuh ke default_branch_id', function () {
    $defaultBranch = makeTestBranch();
    $unauthorizedBranch = makeTestBranch();
    $user = User::factory()->create(['default_branch_id' => $defaultBranch->id]);
    Auth::login($user);

    runMiddleware(requestWithSession(['active_branch_id' => $unauthorizedBranch->id]));

    expect(app(BranchContext::class)->current())->toBe($defaultBranch->id);
});

it('tidak mengubah BranchContext bila tidak ada pengguna terautentikasi', function () {
    app(BranchContext::class)->set('nilai-sebelumnya');

    runMiddleware(requestWithSession());

    expect(app(BranchContext::class)->current())->toBe('nilai-sebelumnya');
});

it('bekerja tanpa sesi sama sekali — jatuh ke default_branch_id', function () {
    $branch = makeTestBranch();
    $user = User::factory()->create(['default_branch_id' => $branch->id]);
    Auth::login($user);

    $request = Request::create('/admin');
    runMiddleware($request);

    expect(app(BranchContext::class)->current())->toBe($branch->id);
});

/**
 * Bug nyata ditemukan lewat log produksi (BUKAN test — Livewire::test()
 * diam-diam melewati SELURUH middleware, satu-satunya alasan gap ini
 * lolos tanpa terdeteksi sepanjang proyek ini): `POST /livewire/update`
 * — endpoint TUNGGAL yang menangani SETIAP interaksi Livewire setelah
 * page load awal (tombol "Create"/"Save" pada SEMUA Resource Filament +
 * checkout POS) — didaftarkan Livewire SENDIRI, di luar route group
 * panel Filament maupun `/pos`. Test di bawah memakai HTTP POST ASLI ke
 * route itu (bukan `Livewire::test()`) — payload tidak perlu valid,
 * middleware berjalan SEBELUM handler Livewire memproses body, cukup
 * membuktikan middleware benar-benar terpanggil pada route sungguhan.
 * Diperbaiki `AppServiceProvider::boot()` via `Livewire::setUpdateRoute()`.
 */
it('SetActiveBranchContext benar-benar berjalan pada route /livewire/update sungguhan, bukan hanya page load', function () {
    $branch = makeTestBranch();
    $user = User::factory()->create(['default_branch_id' => $branch->id]);

    $this->actingAs($user)->post('/livewire/update', []);

    expect(app(BranchContext::class)->current())->toBe($branch->id);
});
