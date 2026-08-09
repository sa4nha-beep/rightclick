<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Settings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tanpa hapus dan tanpa aksi massal — lihat dokblok SettingResource/
 * EditSetting. `EditAction` sendiri disaring `SettingPolicy` (manage_settings
 * + node HQ); di node cabang atau bagi peran selain Owner, kolomnya tetap
 * terlihat (baca) tapi tombol Ubah tidak akan pernah muncul.
 */
class SettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Kunci')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('value')
                    ->label('Nilai')
                    ->formatStateUsing(fn (mixed $state): string => match (true) {
                        is_bool($state) => $state ? 'Ya' : 'Tidak',
                        is_float($state) => number_format($state * 100, 0).'%',
                        is_int($state) => 'Rp '.number_format($state, 0, ',', '.'),
                        default => (string) $state,
                    }),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(60)
                    ->wrap(),
            ])
            ->defaultSort('key')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
