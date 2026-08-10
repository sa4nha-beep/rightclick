<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBalances\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class StockBalanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('branch.name')->label('Cabang'),
                        TextEntry::make('product.sku')->label('SKU'),
                        TextEntry::make('product.name')->label('Produk'),
                        TextEntry::make('qty_on_hand')->label('Kuantitas Tersedia')->numeric(4),
                        TextEntry::make('updated_at')->label('Terakhir Diperbarui')->dateTime('d M Y H:i'),
                    ]),
            ]);
    }
}
