<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBalances\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockBalancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable(),
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable(),
                TextColumn::make('qty_on_hand')
                    ->label('Kuantitas Tersedia')
                    ->numeric(4)
                    ->badge()
                    ->color(fn (string $state): string => (float) $state > 0 ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d M Y H:i'),
            ])
            ->defaultSort('product.name')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
