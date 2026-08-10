<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockMutations\Schemas;

use App\Domain\Inventory\Enums\StockMutationType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class StockMutationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i:s'),
                        TextEntry::make('branch.name')->label('Cabang'),
                        TextEntry::make('product.sku')->label('SKU'),
                        TextEntry::make('mutation_type')
                            ->label('Jenis')
                            ->badge()
                            ->formatStateUsing(fn (StockMutationType $state): string => $state->label()),
                        TextEntry::make('quantity')->label('Kuantitas')->numeric(4),
                        TextEntry::make('unit_cost')
                            ->label('Unit Cost')
                            ->money('IDR')
                            ->visible(fn (): bool => (bool) Auth::user()?->can('view_stock_cost')),
                        TextEntry::make('stockBatch.id')->label('Batch')->copyable(),
                        TextEntry::make('reference_type')
                            ->label('Jenis Dokumen')
                            ->formatStateUsing(fn (string $state): string => class_basename($state)),
                        TextEntry::make('reference_id')->label('ID Dokumen')->copyable(),
                    ]),
            ]);
    }
}
