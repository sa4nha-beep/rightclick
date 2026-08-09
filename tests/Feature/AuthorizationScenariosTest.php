<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\AuditAction;
use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Setting;
use App\Infrastructure\Persistence\Models\User;
use App\Infrastructure\Persistence\Policies\ApprovalPolicy;
use App\Infrastructure\Persistence\Policies\AuditLogPolicy;
use App\Infrastructure\Persistence\Policies\BranchPolicy;
use App\Infrastructure\Persistence\Policies\SettingPolicy;
use App\Infrastructure\Persistence\Support\BranchContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T1.13 — Pengujian otorisasi negatif PT1–PT16 (HS-PERM-RIGHTCLICK-v1.2 §7).
 *
 * CAKUPAN (disepakati dengan pengguna sebelum implementasi — 8 dari 16
 * skenario menyebut model bisnis yang belum dibangun di Fase 1):
 *
 * Diuji di sini — mekanisme otorisasi generik, memakai model yang sudah
 * ada (Branch/User/Setting/Approval/AuditLog), MEMBUKTIKAN mekanismenya,
 * bukan mereproduksi skenario bisnis literalnya kata demi kata:
 *   PT3, PT4, PT6, PT8, PT9, PT10, PT11
 *
 * DITUNDA ke fase modul terkait — skenario menyebut Sale/PurchaseOrder/
 * Product/StockAdjustment/Employee yang belum ada modelnya sama sekali:
 *   PT1  (Kasir → laporan HPP)            → Fase 4 (Sales/POS)
 *   PT2  (endpoint cogs_amount)           → Fase 4 (Sales/POS)
 *   PT5  (void penjualan final)           → Fase 4 (Sales/POS)
 *   PT7  (diskon POS → approval, AP-01)   → Fase 4 (Sales/POS)
 *   PT12 (Gudang buka PO tanpa harga)     → Fase 5 (Procurement)
 *   PT13 (diskon Rp150rb → approval TH1)  → Fase 4 (Sales/POS)
 *   PT14 (diskon 2 baris total TA2)       → Fase 4 (Sales/POS)
 *   PT15 (penyesuaian stok TH3b)          → Fase 3 (Inventory)
 *   PT16 (harga di bawah HPP TH5c)        → Fase 2 (Master Data)
 *
 * Modul-modul di atas WAJIB menambahkan test PT-nya sendiri saat dibangun
 * — dicatat di CLAUDE.md §11/§13, bukan celah tersembunyi. Mekanisme yang
 * mereka semua pakai (ApprovalService, threshold dari Setting, Policy
 * per model) sudah teruji generik di sini dan di ApprovalServiceTest.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

/**
 * PT3 — Admin Cabang A membuka dokumen milik Cabang B → 404, bukan 403.
 *
 * BranchScope menyaring di lapisan QUERY, sebelum Policy sempat berjalan
 * — dokumen lintas cabang tidak pernah "ditemukan lalu ditolak", ia
 * memang tidak pernah muncul dalam hasil query sama sekali. Approval
 * dipakai sebagai model uji (branch-scoped, sudah ada sejak T1.9);
 * mekanismenya sama untuk model branch-scoped apa pun (Sale, PO, dst.
 * saat dibangun).
 */
it('PT3 — dokumen milik cabang lain tidak terlihat sama sekali (404, bukan 403)', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $requester = User::factory()->for($branchB, 'defaultBranch')->create();

    $approvalInBranchB = Approval::create([
        'branch_id' => $branchB->id,
        'approvable_type' => Branch::class,
        'approvable_id' => $branchB->id,
        'requested_by' => $requester->id,
        'status' => ApprovalStatus::Pending,
        'requested_at' => now(),
    ]);

    app(BranchContext::class)->set($branchA->id);

    // Bukan exception "akses ditolak" — query murni tidak menemukannya.
    expect(Approval::query()->find($approvalInBranchB->id))->toBeNull();

    app(BranchContext::class)->clear();
});

/**
 * PT4 — Peran tanpa permission mencoba aksi di luar wewenangnya → 403.
 *
 * Skenario asli menyebut "Gudang membuka daftar payroll" — tabel payroll
 * belum ada (Fase 2, T2.4 belum dikerjakan). Digeneralisasi: peran Gudang
 * mencoba mengelola `settings`, permission yang memang tidak pernah
 * diberikan padanya (lihat PermissionSeeder — hanya Owner yang punya
 * `manage_settings`).
 */
it('PT4 — Gudang ditolak mengakses resource di luar wewenangnya', function () {
    $branch = Branch::factory()->create();
    $gudang = User::factory()->for($branch, 'defaultBranch')->create();
    $gudang->assignRole('gudang');

    expect($gudang->can('manage_settings'))->toBeFalse();

    $policy = new SettingPolicy;
    expect($policy->viewAny($gudang))->toBeFalse();
});

/**
 * PT6 — Bahkan Owner tidak bisa menghapus baris audit_logs (P1: "Tidak
 * ada peran yang dapat update atau delete audit_logs — termasuk Owner").
 *
 * Diuji di lapisan Policy (satu-satunya jalur akses tulis yang ada saat
 * ini — belum ada endpoint/Ops API terpisah untuk audit_logs, itu
 * lingkup T1.14). update/delete/forceDelete SELALU false tanpa syarat,
 * tidak bergantung permission apa pun yang dimiliki pemanggil.
 */
it('PT6 — Owner tetap tidak bisa menghapus atau mengubah audit_logs', function () {
    $branch = Branch::factory()->create();
    $owner = User::factory()->for($branch, 'defaultBranch')->create();
    $owner->assignRole('owner');

    $log = AuditLog::create([
        'actor_id' => $owner->id,
        'action' => AuditAction::Created,
        'model_type' => Branch::class,
        'model_id' => $branch->id,
        'branch_id' => $branch->id,
        'created_at' => now(),
    ]);

    $policy = new AuditLogPolicy;

    expect($policy->update($owner, $log))->toBeFalse()
        ->and($policy->delete($owner, $log))->toBeFalse()
        ->and($policy->forceDelete($owner, $log))->toBeFalse();
});

/**
 * PT8 — Admin tidak bisa menulis master data (tabel REPLICATED) saat
 * berada di node cabang, walau permission-nya sendiri lengkap.
 *
 * Skenario asli menyebut "produk" (belum ada modelnya, T2.3 belum
 * dikerjakan) — `branches` sudah REPLICATED sejak T1.4 dan memakai
 * mekanisme penjagaan yang sama persis (`GuardsMasterDataWrites`) yang
 * akan dipakai `ProductPolicy` nanti.
 */
it('PT8 — Admin ditolak menulis master data saat node adalah cabang', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->for($branch, 'defaultBranch')->create();
    $admin->assignRole('admin');

    $policy = new BranchPolicy;

    // Di HQ, Admin berwenang penuh.
    expect($policy->create($admin))->toBeTrue();

    // Node yang sama, hak yang sama, tapi node sekarang cabang — ditolak.
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->create($admin))->toBeFalse();
});

/**
 * PT9 — Viewer tidak bisa membuat dokumen apa pun, di seluruh modul yang
 * sudah ada Policy-nya sampai saat ini.
 */
it('PT9 — Viewer ditolak membuat dokumen pada seluruh modul yang tersedia', function () {
    $branch = Branch::factory()->create();
    $viewer = User::factory()->for($branch, 'defaultBranch')->create();
    $viewer->assignRole('viewer');

    expect((new BranchPolicy)->create($viewer))->toBeFalse()
        ->and((new SettingPolicy)->create($viewer))->toBeFalse()
        ->and((new ApprovalPolicy)->create($viewer))->toBeFalse();
});

/**
 * PT10 — Pengguna dinonaktifkan saat sesi aktif berjalan → aksi
 * berikutnya ditolak, sesi diakhiri.
 *
 * Diuji lewat HTTP sungguhan (bukan Policy unit test) karena inilah
 * satu-satunya cara membuktikan `User::canAccessPanel()` benar-benar
 * terpanggil pada rantai permintaan panel sungguhan, bukan cuma ada
 * sebagai method yang tidak pernah dipanggil. (Percobaan awal memakai
 * middleware kustom terpisah sebelum `Authenticate::class` milik
 * Filament — dibatalkan karena tidak konsisten terpanggil pada jalur
 * Livewire full-page; lihat dokblok `canAccessPanel()`.)
 */
it('PT10 — pengguna yang dinonaktifkan ditolak pada permintaan berikutnya dan sesinya berakhir', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->for($branch, 'defaultBranch')->create(['is_active' => true]);
    $user->assignRole('viewer');

    $this->actingAs($user);

    // Sesi masih valid — halaman terlindungi dapat diakses.
    $this->get('/admin')->assertOk();

    $user->update(['is_active' => false]);

    $response = $this->get('/admin');
    $response->assertForbidden();

    // Sesi benar-benar diakhiri, bukan sekadar permintaan ini yang ditolak.
    $this->assertGuest();
});

it('PT10 — locally_disabled_at (darurat offline) juga mengakhiri sesi', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->for($branch, 'defaultBranch')->create(['is_active' => true]);
    $user->assignRole('viewer');

    $this->actingAs($user);
    $this->get('/admin')->assertOk();

    $user->update(['locally_disabled_at' => now()]);

    $this->get('/admin')->assertForbidden();
    $this->assertGuest();
});

/**
 * PT11 — Model transaksi tanpa Policy menggagalkan CI.
 *
 * Sudah diimplementasikan di tests/Arch/LayeringTest.php sejak T1.5
 * ("setiap model Eloquent RIGHTCLICK memiliki Policy") — dicatat di sini
 * sebagai referensi silang, bukan diduplikasi.
 */
it('PT11 — referensi: architecture test Policy-per-model ada di tests/Arch/LayeringTest.php', function () {
    expect(file_exists(base_path('tests/Arch/LayeringTest.php')))->toBeTrue();
})->skip('Dokumentasi referensi silang — pengujian sungguhan berjalan di suite Arch, bukan Feature.');
