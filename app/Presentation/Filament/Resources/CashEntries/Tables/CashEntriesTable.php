<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashEntries\Tables;

use App\Domain\Finance\Enums\CashEntryType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CashEntriesTable
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
                TextColumn::make('entry_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (CashEntryType $state): string => $state->label())
                    ->color(fn (CashEntryType $state): string => match ($state) {
                        CashEntryType::VoidReversal => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->color(fn (string $state): string => (float) $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('reference_type')
                    ->label('Dokumen')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                SelectFilter::make('entry_type')
                    ->label('Jenis')
                    ->options(array_combine(
                        array_map(fn (CashEntryType $c): string => $c->value, CashEntryType::cases()),
                        array_map(fn (CashEntryType $c): string => $c->label(), CashEntryType::cases()),
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
