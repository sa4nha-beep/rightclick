<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class StockBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('branch.name')->label('Cabang'),
                        TextEntry::make('product.sku')->label('SKU'),
                        TextEntry::make('product.name')->label('Produk'),
                        TextEntry::make('received_at')->label('Diterima')->dateTime('d M Y H:i'),
                        TextEntry::make('qty_received')->label('Qty Diterima')->numeric(4),
                        TextEntry::make('qty_remaining')->label('Qty Tersisa')->numeric(4),
                        TextEntry::make('unit_cost')
                            ->label('Unit Cost (termasuk PPN)')
                            ->money('IDR')
                            ->visible(fn (): bool => (bool) Auth::user()?->can('view_stock_cost')),
                        TextEntry::make('reference_type')
                            ->label('Jenis Dokumen Asal')
                            ->formatStateUsing(fn (string $state): string => class_basename($state)),
                        TextEntry::make('reference_id')->label('ID Dokumen Asal')->copyable(),
                    ]),
            ]);
    }
}
