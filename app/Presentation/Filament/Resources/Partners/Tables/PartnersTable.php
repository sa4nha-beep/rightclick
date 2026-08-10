<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Partners\Tables;

use App\Domain\Shared\Enums\PartnerType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('partner_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (PartnerType $state): string => $state->label()),
                TextColumn::make('phone')
                    ->label('Telepon'),
                TextColumn::make('city')
                    ->label('Kota'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('partner_type')
                    ->label('Jenis Mitra')
                    ->options(collect(PartnerType::cases())->mapWithKeys(
                        fn (PartnerType $type): array => [$type->value => $type->label()]
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
