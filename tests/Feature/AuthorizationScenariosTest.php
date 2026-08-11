<?php

declare(strict_types=1);

use App\Application\Actions\ChangeProductSellingPriceAction;
use App\Application\Actions\FinalizeSaleAction;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\AuditAction;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\AuditLog;
use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\Service;
use App\Infrastructure\Persistence\Models\Setting;
use App\Infrastructure\Persistence\Models\StockBatch;
use App\Infrastructure\Persistence\Models\User;
use App\Infrastructure\Persistence\Policies\ApprovalPolicy;
use App\Infrastructure\Persistence\Policies\AuditLogPolicy;
use App\Infrastructure\Persistence\Policies\BranchPolicy;
use App\Infrastructure\Persistence\Policies\PurchaseOrderPolicy;
use App\Infrastructure\Persistence\Policies\SalePolicy;
use App\Infrastructure\Persistence\Policies\SettingPolicy;
use App\Infrastructure\Persistence\Support\BranchContext;
use App\Presentation\Pos\Livewire\PosTerminal;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

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
 * DITUTUP di sini (T5.9) — Sale/CashierShift sudah ada sejak Fase 4,
 * ditegakkan dengan PERAN NYATA (`assignRole('kasir')` via PermissionSeeder,
 * bukan permission ad-hoc `makeTestUser()`), menutup kesenjangan antara
 * "mekanisme Policy-nya benar" (sudah teruji sejak T4.1/T4.2) dan "peran
 * Kasir yang SUNGGUHAN memang tidak diberi permission itu":
 *   PT1  (Kasir → laporan HPP)            → lihat "PT1" di bawah
 *   PT2  (endpoint cogs_amount)           → lihat "PT2" di bawah
 *   PT5  (void penjualan final)           → lihat "PT5" di bawah
 *   PT7  (diskon POS → approval, AP-01)   → lihat "PT7/PT13" di bawah
 *   PT13 (diskon Rp150rb → approval TH1)  → lihat "PT7/PT13" di bawah
 *   PT14 (diskon 2 baris total TA2)       → lihat "PT14" di bawah
 *
 * DITUTUP di sini (pasca-Fase 5) — dikonfirmasi pengguna (AskUserQuestion)
 * sebelum implementasi, karena keduanya butuh keputusan desain di luar
 * sekadar "tulis test untuk mekanisme yang sudah ada":
 *   PT12 (Gudang buka PO tanpa harga)     → lihat "PT12" di bawah. Keputusan:
 *     Gudang TETAP tidak diberi akses Purchase Order sama sekali (bukan
 *     akses baca dengan harga disembunyikan) — bug NYATA ditemukan selama
 *     investigasi (`view_goods_receipt` hilang dari permission Gudang,
 *     lihat `PermissionSeeder`) diperbaiki di commit yang sama.
 *   PT16 (harga di bawah HPP TH5c)        → lihat `ProductPolicy`/
 *     `ChangeProductSellingPriceAction`/`ProductPriceApprovalTest.php`.
 *     Keputusan: TH5a/TH5b/TH5c dibangun BERSAMAAN (satu mekanisme
 *     approval baru dipakai bertiga), bukan hanya TH5c — lihat docblock
 *     `ChangeProductSellingPriceAction` untuk detail lengkap.
 * PT15 (penyesuaian stok TH3b) — ✅ sudah ditutup T3.5, dicatat sebagai
 * referensi silang saja.
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

/**
 * PT1 — Kasir tidak pernah menerima data HPP/margin lewat katalog POS
 * (POS-06/P6). `PosTerminal::products()`/`services()` (T4.4) HANYA
 * men-select kolom aman secara eksplisit — dibuktikan di sini dengan peran
 * `kasir` SUNGGUHAN (bukan permission ad-hoc) memanggil method itu lewat
 * komponen Livewire yang benar-benar di-mount, lalu memeriksa TIDAK ADA
 * satu pun kunci atribut ber-bau biaya/margin yang lolos ke koleksi hasil.
 */
it('PT1 — Kasir tidak pernah menerima data HPP/margin dari katalog POS', function () {
    $branch = Branch::factory()->create();
    $kasir = User::factory()->for($branch, 'defaultBranch')->create();
    $kasir->assignRole('kasir');
    $this->actingAs($kasir);
    app(BranchContext::class)->set($branch->id);

    Product::factory()->create(['selling_price' => '20000.00']);
    Service::factory()->create(['price' => '50000.00']);

    $component = Livewire::test(PosTerminal::class);
    $products = $component->instance()->products();
    $services = $component->instance()->services();

    expect($products)->not->toBeEmpty()
        ->and($services)->not->toBeEmpty();

    $costLikeKeys = ['unit_cost', 'unit_cost_snapshot', 'cost_amount', 'margin', 'purchase_price'];

    foreach ($products as $product) {
        expect(array_intersect($costLikeKeys, array_keys($product->getAttributes())))->toBeEmpty();
    }
    foreach ($services as $service) {
        expect(array_intersect($costLikeKeys, array_keys($service->getAttributes())))->toBeEmpty();
    }

    app(BranchContext::class)->clear();
});

/**
 * PT2 — Tidak ada "endpoint" back-office (SaleResource) yang mengekspos
 * `unit_cost_snapshot`/margin ke peran manapun (P6 — disaring di lapisan
 * skema/query, bukan disembunyikan di tampilan).
 *
 * Berbeda dari Inventory (T3.3, permission `view_stock_cost` +
 * `getEloquentQuery()` penyaring kolom) — Sales BELUM punya UI yang
 * menampilkan `sale_items` sama sekali (tidak ada halaman View/relation
 * manager), jadi saat ini TIDAK ADA yang perlu disaring karena memang
 * tidak pernah di-select/dirender di mana pun. Test ini adalah TRIPWIRE:
 * scan teks mentah skema Table/Form (pola sama arsitektur test T5.7/T5.8
 * yang membaca berkas apa adanya) — bila sesi mendatang menambahkan kolom
 * biaya ke `SalesTable`/`SaleForm`, test ini GAGAL, memaksa penambahan
 * gerbang permission `view_sale_cost` + filter query (pola T3.3) saat itu
 * juga, bukan lolos diam-diam.
 */
it('PT2 — skema back-office penjualan (SalesTable/SaleForm) tidak pernah menyebut kolom biaya/margin', function () {
    $tableSource = file_get_contents(app_path('Presentation/Filament/Resources/Sales/Tables/SalesTable.php'));
    $formSource = file_get_contents(app_path('Presentation/Filament/Resources/Sales/Schemas/SaleForm.php'));

    expect($tableSource)->not->toBeFalse()->and($formSource)->not->toBeFalse();

    foreach (['unit_cost', 'cost_amount', 'margin', 'purchase_price'] as $needle) {
        expect($tableSource)->not->toContain($needle)
            ->and($formSource)->not->toContain($needle);
    }
});

/**
 * PT5 — Kasir (PERAN NYATA, via PermissionSeeder) ditolak membatalkan
 * penjualan final. `SalePolicyTest` sudah membuktikan mekanisme
 * `void_sale` generik (permission ad-hoc) — test ini menutup rantai ke
 * peran `kasir` SUNGGUHAN yang dipakai produksi.
 */
it('PT5 — Kasir (peran nyata) ditolak membatalkan penjualan final', function () {
    $branch = Branch::factory()->create();
    $kasir = User::factory()->for($branch, 'defaultBranch')->create();
    $kasir->assignRole('kasir');

    $sale = Sale::factory()->create([
        'branch_id' => $branch->id,
        'state' => DocumentState::Final,
        'finalized_at' => now(),
    ]);

    expect($kasir->can('void_sale'))->toBeFalse()
        ->and((new SalePolicy)->void($kasir, $sale))->toBeFalse();
});

/**
 * PT7/PT13 — diskon POS Kasir (PERAN NYATA) melebihi TH1 (Rp100.000)
 * membuat dokumen tetap draft dan menerbitkan Approval tertunda (AP-01),
 * BUKAN diterapkan langsung maupun ditolak keras. `FinalizeSaleActionTest`
 * sudah membuktikan mekanisme ambang dengan `makeTestUser(['create_sale'])`
 * — test ini menutup rantai ke peran `kasir` SUNGGUHAN, dan membuktikan
 * Kasir sendiri tidak berwenang menyetujui diskonnya sendiri
 * (`manage_sale_discount` bukan permission Kasir).
 */
it('PT7/PT13 — diskon Kasir (peran nyata) Rp150.000 > TH1 membuat Approval tertunda, bukan diterapkan langsung', function () {
    $branch = Branch::factory()->create();
    $kasir = User::factory()->for($branch, 'defaultBranch')->create();
    $kasir->assignRole('kasir');
    $this->actingAs($kasir);

    $shift = CashierShift::factory()->create(['branch_id' => $branch->id, 'cashier_id' => $kasir->id]);
    $service = Service::factory()->create(['price' => '500000.00']);
    $sale = Sale::factory()->create([
        'branch_id' => $branch->id,
        'cashier_shift_id' => $shift->id,
        'discount_amount' => '150000.00',
    ]);
    $sale->items()->create(['service_id' => $service->id, 'quantity' => '1.0000', 'unit_price' => '500000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '350000.00']);

    $result = app(FinalizeSaleAction::class)->execute($sale);

    expect($result->state)->toBe(DocumentState::Draft)
        ->and($result->document_number)->toBeNull();

    $approval = Approval::query()
        ->where('approvable_type', $sale->getMorphClass())
        ->where('approvable_id', $sale->id)
        ->sole();

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->requested_by)->toBe($kasir->id)
        ->and($kasir->can('manage_sale_discount'))->toBeFalse();
});

/**
 * PT14 (TA2) — diskon dipecah ke DUA baris (mis. Rp60.000 + Rp60.000)
 * tetap ditegakkan pada TOTAL transaksi, bukan lolos per baris. Struktur
 * data sendiri sudah mencegah ini — `discount_amount` adalah SATU kolom
 * di level dokumen `Sale` (dikonfirmasi: `sale_items` TIDAK PERNAH punya
 * kolom diskon sendiri) — jadi memecah diskon ke beberapa baris justru
 * tidak mengubah apa pun; harus tetap digabung jadi satu nilai di sini.
 */
it('PT14 — diskon dipecah dua baris tetap ditegakkan pada TOTAL transaksi (TA2)', function () {
    expect(Schema::hasColumn('sale_items', 'discount_amount'))->toBeFalse();

    $branch = Branch::factory()->create();
    $kasir = User::factory()->for($branch, 'defaultBranch')->create();
    $kasir->assignRole('kasir');
    $this->actingAs($kasir);

    $shift = CashierShift::factory()->create(['branch_id' => $branch->id, 'cashier_id' => $kasir->id]);
    $serviceA = Service::factory()->create(['price' => '200000.00']);
    $serviceB = Service::factory()->create(['price' => '200000.00']);

    // Rp60.000 (baris A) + Rp60.000 (baris B) digabung menjadi SATU nilai
    // Rp120.000 di level dokumen — melebihi TH1 (Rp100.000).
    $sale = Sale::factory()->create([
        'branch_id' => $branch->id,
        'cashier_shift_id' => $shift->id,
        'discount_amount' => '120000.00',
    ]);
    $sale->items()->create(['service_id' => $serviceA->id, 'quantity' => '1.0000', 'unit_price' => '200000.00']);
    $sale->items()->create(['service_id' => $serviceB->id, 'quantity' => '1.0000', 'unit_price' => '200000.00']);
    $sale->payments()->create(['method' => 'cash', 'amount' => '280000.00']);

    $result = app(FinalizeSaleAction::class)->execute($sale);

    expect($result->state)->toBe(DocumentState::Draft);

    expect(Approval::query()
        ->where('approvable_type', $sale->getMorphClass())
        ->where('approvable_id', $sale->id)
        ->where('status', ApprovalStatus::Pending)
        ->exists())->toBeTrue();
});

/**
 * PT12 — Gudang (PERAN NYATA) dapat menjalankan pekerjaan intinya
 * (menerima barang) tapi tidak pernah menerima harga Purchase Order.
 *
 * Bug NYATA ditemukan saat investigasi (bukan hanya kesenjangan test):
 * `view_goods_receipt` hilang dari `$gudangPermissions` sejak awal
 * (`PermissionSeeder`) — Filament Create page mensyaratkan `viewAny` DAN
 * `create` untuk mount (gotcha yang sama T5.6), jadi Gudang sebelumnya
 * TIDAK PERNAH BISA membuka halaman Create Goods Receipt sama sekali.
 * Lolos tanpa terdeteksi karena `GoodsReceiptResourceTest` selalu memakai
 * permission ad-hoc, bukan peran `gudang` hasil seed sungguhan — pola gap
 * yang sama dengan PT1/PT2/PT5/PT7/PT13/PT14. Diperbaiki di commit yang
 * sama dengan test ini.
 *
 * Keputusan desain (dikonfirmasi pengguna): Gudang TETAP tidak diberi
 * akses Purchase Order sama sekali — bukan akses baca dengan harga
 * disembunyikan. PT12 ditutup dengan membuktikan harga PO secara
 * STRUKTURAL tidak pernah bisa sampai ke Gudang: mereka tidak pernah bisa
 * membuka `PurchaseOrderResource` sama sekali, dan satu-satunya titik
 * singgung dengan PO (field `purchase_order_id` di form Goods Receipt,
 * murni ketertelusuran lewat `document_number`) tidak pernah menyebut
 * `unit_price` di skemanya.
 */
it('PT12 — Gudang (peran nyata) dapat menerima barang tapi tidak pernah melihat harga Purchase Order', function () {
    $branch = Branch::factory()->create();
    $gudang = User::factory()->for($branch, 'defaultBranch')->create();
    $gudang->assignRole('gudang');

    expect($gudang->can('perform_goods_receipt'))->toBeTrue()
        ->and($gudang->can('view_goods_receipt'))->toBeTrue();

    expect($gudang->can('view_purchase_orders'))->toBeFalse()
        ->and($gudang->can('create_purchase_order'))->toBeFalse()
        ->and((new PurchaseOrderPolicy)->viewAny($gudang))->toBeFalse();

    $formSource = file_get_contents(app_path('Presentation/Filament/Resources/GoodsReceipts/Schemas/GoodsReceiptForm.php'));

    expect($formSource)->not->toBeFalse()
        ->and($formSource)->not->toContain('unit_price');
});

/**
 * PT16 — perubahan harga jual Produk di bawah HPP batch tertua (TH5c)
 * SELALU memicu Approval tertunda, tidak peduli aktor punya permission
 * `manage_product_prices` sekalipun (Admin, bukan sekadar peran tanpa izin
 * sama sekali seperti PT1-PT14). `ChangeProductSellingPriceActionTest.php`
 * sudah membuktikan mekanisme TH5a/TH5b/TH5c secara generik lewat
 * permission ad-hoc — test ini menutup rantai ke peran `admin` hasil seed
 * SUNGGUHAN, pola sama PT7/PT13.
 */
it('PT16 — Admin (peran nyata) mengajukan harga di bawah HPP batch tertua tetap menunggu Approval', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->for($branch, 'defaultBranch')->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect($admin->can('manage_product_prices'))->toBeTrue();

    $product = Product::factory()->create(['selling_price' => '100000.00']);
    StockBatch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'unit_cost' => '96000.00',
        'qty_received' => '5.0000',
        'qty_remaining' => '5.0000',
        'received_at' => now()->subDay(),
    ]);

    // Rp95.500 — turun hanya 4,5% (di bawah TH5b 5%) tapi di bawah HPP
    // batch tertua (Rp96.000): murni TH5c yang memicu.
    $result = app(ChangeProductSellingPriceAction::class)->execute($product, '95500.00');

    expect($result)->toBeInstanceOf(Approval::class)
        ->and($result->status)->toBe(ApprovalStatus::Pending);

    expect((string) $product->fresh()->selling_price)->toEqual('100000.00');
});
