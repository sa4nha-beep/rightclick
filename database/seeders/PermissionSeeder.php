<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\Permission;
use App\Infrastructure\Persistence\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seeder phase 2 (T1.5): Five roles and 58 permissions.
     *
     * CLAUDE.md §10 — Otorisasi
     * Role hierarchy: owner > admin > {kasir, gudang} > viewer
     *
     * Permission domains (58 total):
     *   Branches (5), Users (7), Partners (6), Products (8),
     *   Inventory (14), Sales (10), Procurement (6), Financials (6)
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

            // Inventory (14)
            'view_stock',
            'view_batches',
            'view_stock_mutations',
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

            // Procurement (6)
            'view_purchase_orders',
            'create_purchase_order',
            'edit_purchase_order',
            'approve_purchase_order',
            'view_goods_receipt',
            'approve_goods_receipt',

            // Financials (6)
            'view_cash_entries',
            'record_cash_entry',
            'approve_cash_entry',
            'view_receivables',
            'view_payables',
            'manage_financial_settings',
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

        // Admin — semua kecuali delete/manage
        $adminPermissions = array_filter($permissions, function ($p) {
            return ! str_contains($p, 'delete_') && ! str_contains($p, 'manage_emergency');
        });
        $admin->syncPermissions($adminPermissions);

        // Kasir — sales operations saja
        $kasirPermissions = [
            'view_sales',
            'create_sale',
            'view_sale_returns',
            'create_sale_return',
            'close_cashier_shift',
            'view_cashier_shift',
            'view_products',
            'view_stock',
        ];
        $kasir->syncPermissions($kasirPermissions);

        // Gudang — inventory operations saja
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
