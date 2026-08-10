<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Policies;

use App\Infrastructure\Persistence\Models\GoodsReceiptLine;
use App\Infrastructure\Persistence\Models\User;

/**
 * Sama pola dengan `PurchaseOrderLinePolicy`/`SaleItemPolicy` — tanpa API
 * tulis independen.
 */
class GoodsReceiptLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_goods_receipt');
    }

    public function view(User $user, GoodsReceiptLine $goodsReceiptLine): bool
    {
        return $user->can('view_goods_receipt');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GoodsReceiptLine $goodsReceiptLine): bool
    {
        return false;
    }

    public function delete(User $user, GoodsReceiptLine $goodsReceiptLine): bool
    {
        return false;
    }

    public function restore(User $user, GoodsReceiptLine $goodsReceiptLine): bool
    {
        return false;
    }

    public function forceDelete(User $user, GoodsReceiptLine $goodsReceiptLine): bool
    {
        return false;
    }
}
