<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBatches\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * P6 — kolom `unit_cost` disaring di lapisan query lewat
 * `StockBatchResource::getEloquentQuery()`, bukan di sini. `visible()` pada
 * kolom `unit_cost` di bawah adalah pertahanan berlapis UI, bukan kontrol
 * utamanya.
 */
class StockBatchesTable
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
                TextColumn::make('received_at')
                    ->label('Diterima')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('qty_received')
                    ->label('Qty Diterima')
                    ->numeric(4),
                TextColumn::make('qty_remaining')
                    ->label('Qty Tersisa')
                    ->numeric(4)
                    ->badge()
                    ->color(fn (string $state): string => (float) $state > 0 ? 'success' : 'gray'),
                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('IDR')
                    ->visible(fn (): bool => (bool) Auth::user()?->can('view_stock_cost')),
                TextColumn::make('reference_type')
                    ->label('Dokumen Asal')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
            ])
            ->defaultSort('received_at', 'desc')
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
