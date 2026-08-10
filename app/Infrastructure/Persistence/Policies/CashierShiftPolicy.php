<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\User;
use App\Infrastructure\Persistence\Support\BranchContext;

/**
 * Otorisasi shift kasir (T4.1). Hanya `close_cashier_shift` yang diseeded
 * (T1.9/PermissionSeeder) untuk siklus hidup shift — TIDAK ADA permission
 * `open_cashier_shift` terpisah. Direuse dengan sengaja untuk `create()`:
 * siapa pun yang boleh MENUTUP shift juga boleh MEMBUKAnya (satu permission
 * menggerbang seluruh siklus, sama pola dengan `perform_adjustment` pada
 * `StockAdjustmentPolicy`).
 *
 * `create()` juga menolak bila aktor SUDAH memiliki shift `draft` (terbuka)
 * di cabang aktifnya — index parsial unik di database (migration) adalah
 * jaring pengaman terakhir, tapi Policy mencegah error 500 mentah dari
 * pelanggaran constraint dan memberi pesan yang bisa ditindaklanjuti lewat
 * `create_cashier_shift` alih-alih exception SQL.
 *
 * `void()` digerbang `void_sale` (BUKAN `close_cashier_shift`) — sengaja
 * DIREUSE dari permission penjualan: `void_sale` TIDAK dimiliki Kasir
 * (PermissionSeeder), memastikan kasir tidak bisa membatalkan sendiri
 * catatan selisih kas shift yang sudah ditutup (AC-16) — hanya Admin/Owner.
 */
class CashierShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_cashier_shift') || $user->can('close_cashier_shift');
    }

    public function view(User $user, CashierShift $cashierShift): bool
    {
        return $user->can('view_cashier_shift') || $user->can('close_cashier_shift');
    }

    public function create(User $user): bool
    {
        if (! $user->can('close_cashier_shift')) {
            return false;
        }

        $branchId = app(BranchContext::class)->current() ?? $user->default_branch_id;

        return ! CashierShift::query()
            ->where('branch_id', $branchId)
            ->where('cashier_id', $user->id)
            ->where('state', DocumentState::Draft)
            ->exists();
    }

    public function update(User $user, CashierShift $cashierShift): bool
    {
        return $user->can('close_cashier_shift') && $cashierShift->state === DocumentState::Draft;
    }

    public function delete(User $user, CashierShift $cashierShift): bool
    {
        return $user->can('close_cashier_shift') && $cashierShift->state === DocumentState::Draft;
    }

    public function restore(User $user, CashierShift $cashierShift): bool
    {
        return $user->can('close_cashier_shift');
    }

    /**
     * Tidak pernah — soft delete adalah satu-satunya jalur hapus (R5).
     */
    public function forceDelete(User $user, CashierShift $cashierShift): bool
    {
        return false;
    }

    public function close(User $user, CashierShift $cashierShift): bool
    {
        return $user->can('close_cashier_shift') && $cashierShift->state === DocumentState::Draft;
    }

    public function void(User $user, CashierShift $cashierShift): bool
    {
        return $user->can('void_sale') && $cashierShift->state === DocumentState::Final;
    }
}
