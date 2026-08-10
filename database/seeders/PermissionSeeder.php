<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\Permission;
use App\Infrastructure\Persistence\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seeder phase 2 (T1.5): Five roles and 58 permissions.
     * T3.3 menambah permission ke-59: `view_stock_cost`.
     * T5.1 menambah permission ke-69: `void_purchase_order`.
     *
     * CLAUDE.md §10 — Otorisasi
     * Role hierarchy: owner > admin > {kasir, gudang} > viewer
     *
     * Permission domains (69 total setelah T5.1):
     *   Branches (5), Users (7), Partners (6), Products (8),
     *   Inventory (15), Sales (10), Procurement (7), Financials (6),
     *   Platform Settings & Approvals (3), Platform Audit Log (2)
     *
     * `view_stock_cost` (T3.3) — HS-PERM-RIGHTCLICK-v1.2 tidak mendaftar
     * permission ini; ditambahkan karena §2 CLAUDE.md eksplisit menyatakan
     * Gudang "tidak melihat nilai" sementara 14 permission Inventory asli
     * (`view_batches`/`view_stock_mutations`) TIDAK membedakan kuantitas
     * dari `unit_cost` — keduanya tabel yang sama berisi kolom biaya. Tanpa
     * permission terpisah, P6 ("kolom sensitif disaring di lapisan query")
     * tidak bisa ditegakkan untuk Gudang. Owner/Admin/Viewer diberi
     * permission ini; Kasir/Gudang tidak — lihat `StockBatchesTable`/
     * `StockMutationsTable` (T3.3) untuk bagaimana ini menyaring kolom
     * `unit_cost` di lapisan query, bukan hanya menyembunyikannya di tabel.
     *
     * `void_purchase_order` (T5.1) — sama alasan strukturalnya dengan
     * `view_stock_cost`: 6 permission Procurement asli (`view_purchase_orders`/
     * `create_purchase_order`/`edit_purchase_order`/`approve_purchase_order`/
     * `view_goods_receipt`/`approve_goods_receipt`) tidak punya permission
     * dedicated untuk membatalkan (void) PO FINAL — `edit_purchase_order`
     * hanya menggerbang penyuntingan draft (`PurchaseOrderPolicy::update()`).
     * Sales (`void_sale`) dan Inventory (`void_stock_document`) sudah punya
     * ini sejak T1.5; Procurement terlewat. Ditambahkan di T5.1, lihat
     * `PurchaseOrderPolicy::void()`.
     */
    public function run(): void
    {
        $permissions = [
            // Branches (5)
            'view_branches',
            'create_branches',
            'edit_branches',
            'delete_branches',
            'manage_branch_access',

            // Users (7)
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'manage_user_roles',
            'manage_user_branches',
            'manage_emergency_disable',

            // Partners (6)
            'view_partners',
            'create_partners',
            'edit_partners',
            'delete_partners',
            'manage_partner_prices',
            'manage_partner_limits',

            // Products (8)
            'view_products',
            'create_products',
            'edit_products',
            'delete_products',
            'manage_product_prices',
            'manage_product_stock',
            'manage_product_variants',
            'manage_product_discontinue',

            // Inventory (14 dari T1.5, + view_stock_cost T3.3 — lihat catatan di bawah)
            'view_stock',
            'view_batches',
            'view_stock_mutations',
            'view_stock_cost',
            'perform_opname',
            'perform_adjustment',
            'perform_transfer',
            'perform_goods_receipt',
            'review_goods_receipt',
            'perform_stock_return',
            'adjust_opening_balance',
            'view_transfer_history',
            'approve_stock_adjustment',
            'void_stock_document',
            'manage_serial_numbers',

            // Sales (10)
            'view_sales',
            'create_sale',
            'edit_sale',
            'view_sale_returns',
            'create_sale_return',
            'process_sale_return',
            'close_cashier_shift',
            'view_cashier_shift',
            'manage_sale_discount',
            'void_sale',

            // Procurement (7, T5.1 menambah void_purchase_order)
            'view_purchase_orders',
            'create_purchase_order',
            'edit_purchase_order',
            'approve_purchase_order',
            'void_purchase_order',
            'view_goods_receipt',
            'approve_goods_receipt',

            // Financials (6)
            'view_cash_entries',
            'record_cash_entry',
            'approve_cash_entry',
            'view_receivables',
            'view_payables',
            'manage_financial_settings',

            // Platform — Settings & Approvals (3, T1.9)
            'manage_settings',
            'request_approval',
            'decide_approval',

            // Platform — Audit Log (2, T1.14)
            'view_audit_logs',
            'export_audit_logs',
        ];

        // Create all permissions
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web']
            );
        }

        // Create roles
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $kasir = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        $gudang = Role::firstOrCreate(['name' => 'gudang', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        // Owner — semua permission
        $owner->syncPermissions($permissions);

        // Admin — semua kecuali delete/manage. `manage_settings` dikecualikan
        // secara eksplisit: HS-PERM-RIGHTCLICK-v1.2 §4.1 — Owner satu-satunya
        // yang boleh mengubah ambang (TA10). `decide_approval` tetap dimiliki
        // Admin ("⚠️ terbatas" pada matriks — batasan nilai ambang ditegakkan
        // per modul di T2.7/T3.6/T5.1, bukan lewat permission ini).
        // `view_audit_logs` diberikan (matriks: "⚠️ cabangnya" — BranchScope
        // pada model AuditLog otomatis membatasi ke cabang Admin sendiri,
        // T1.6). `export_audit_logs` dikecualikan — Owner saja (matriks §4.1).
        $adminPermissions = array_filter($permissions, function ($p) {
            return ! str_contains($p, 'delete_')
                && ! str_contains($p, 'manage_emergency')
                && $p !== 'manage_settings'
                && $p !== 'export_audit_logs';
        });
        $admin->syncPermissions($adminPermissions);

        // Kasir — sales operations saja. `request_approval` (diskon di atas
        // TH1 tetap tersimpan sebagai draf menunggu approval, AP-01).
        $kasirPermissions = [
            'view_sales',
            'create_sale',
            'view_sale_returns',
            'create_sale_return',
            'close_cashier_shift',
            'view_cashier_shift',
            'view_products',
            'view_stock',
            'request_approval',
        ];
        $kasir->syncPermissions($kasirPermissions);

        // Gudang — inventory operations saja. `request_approval` (penyesuaian
        // di atas TH3 tetap tersimpan menunggu approval).
        $gudangPermissions = [
            'view_stock',
            'view_batches',
            'view_stock_mutations',
            'perform_opname',
            'perform_adjustment',
            'perform_transfer',
            'perform_goods_receipt',
            'perform_stock_return',
            'view_transfer_history',
            'manage_serial_numbers',
            'view_partners',
            'request_approval',
        ];
        $gudang->syncPermissions($gudangPermissions);

        // Viewer — read-only access
        $viewerPermissions = [
            'view_branches',
            'view_users',
            'view_partners',
            'view_products',
            'view_stock',
            'view_batches',
            'view_stock_mutations',
            'view_stock_cost',
            'view_sales',
            'view_sale_returns',
            'view_cashier_shift',
            'view_purchase_orders',
            'view_goods_receipt',
            'view_cash_entries',
            'view_receivables',
            'view_payables',
        ];
        $viewer->syncPermissions($viewerPermissions);
    }
}
