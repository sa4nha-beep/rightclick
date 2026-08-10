<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

/**
 * Arah baris penyesuaian stok (`stock_adjustment_lines.direction`, T3.5).
 */
enum StockAdjustmentDirection: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';

    public function label(): string
    {
        return match ($this) {
            self::Increase => 'Naik',
            self::Decrease => 'Turun',
        };
    }
}
