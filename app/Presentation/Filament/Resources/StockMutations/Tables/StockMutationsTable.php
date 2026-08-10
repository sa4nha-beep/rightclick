<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockMutations\Tables;

use App\Domain\Inventory\Enums\StockMutationType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class StockMutationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Cabang'),
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('mutation_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (StockMutationType $state): string => $state->label())
                    ->color(fn (StockMutationType $state): string => match (true) {
                        $state === StockMutationType::VoidReversal => 'warning',
                        in_array($state, [
                            StockMutationType::Receipt,
                            StockMutationType::AdjustmentIncrease,
                            StockMutationType::OpnameCorrectionIncrease,
                            StockMutationType::TransferIn,
                            StockMutationType::SaleReturn,
                        ], true) => 'success',
                        default => 'danger',
                    }),
                TextColumn::make('quantity')
                    ->label('Kuantitas')
                    ->numeric(4),
                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('IDR')
                    ->visible(fn (): bool => (bool) Auth::user()?->can('view_stock_cost')),
                TextColumn::make('reference_type')
                    ->label('Dokumen')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                SelectFilter::make('mutation_type')
                    ->label('Jenis Mutasi')
                    ->options(array_combine(
                        array_map(fn (StockMutationType $c): string => $c->value, StockMutationType::cases()),
                        array_map(fn (StockMutationType $c): string => $c->label(), StockMutationType::cases()),
                    )),
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
