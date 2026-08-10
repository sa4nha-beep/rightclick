<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\User;

/**
 * Otorisasi penjualan (T4.1). `finalize()` digerbang `create_sale` (BUKAN
 * `edit_sale`) — Kasir memiliki `create_sale` tapi TIDAK `edit_sale`
 * (PermissionSeeder), dan menyelesaikan transaksi POS adalah pekerjaan inti
 * Kasir, bukan Admin. Nilai diskon (TH1/TH2, T4.2) adalah logika bisnis di
 * `FinalizeSaleAction`, bukan otorisasi — sama pola dengan
 * `StockAdjustmentPolicy::finalize()`.
 *
 * `void()` digerbang `void_sale` — permission ini SENGAJA TIDAK dimiliki
 * Kasir (PermissionSeeder), menegakkan CLAUDE.md §2 "Kasir ... tidak dapat
 * void" secara struktural.
 *
 * `approve()` (T4.2, diskon TH1/TH2) — ability terpisah untuk memutuskan
 * Approval yang tertaut dokumen ini, digerbang `manage_sale_discount`
 * (BUKAN `decide_approval` generik `ApprovalPolicy`) — permission ini
 * SENGAJA TIDAK dimiliki Kasir, sama pola dengan `approve_stock_adjustment`
 * pada `StockAdjustmentPolicy` (Admin/Owner saja yang bisa menaikkan
 * diskon Kasir sendiri di atas TH1).
 */
class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_sales');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->can('view_sales');
    }

    public function create(User $user): bool
    {
        return $user->can('create_sale');
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->can('edit_sale') && $sale->state === DocumentState::Draft;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->can('edit_sale') && $sale->state === DocumentState::Draft;
    }

    public function restore(User $user, Sale $sale): bool
    {
        return $user->can('edit_sale');
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, Sale $sale): bool
    {
        return false;
    }

    public function finalize(User $user, Sale $sale): bool
    {
        return $user->can('create_sale') && $sale->state === DocumentState::Draft;
    }

    public function void(User $user, Sale $sale): bool
    {
        return $user->can('void_sale') && $sale->state === DocumentState::Final;
    }

    public function approve(User $user, Sale $sale): bool
    {
        return $user->can('manage_sale_discount') && $sale->state === DocumentState::Draft;
    }
}
