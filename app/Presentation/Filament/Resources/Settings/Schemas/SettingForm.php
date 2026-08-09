<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Settings\Schemas;

use App\Infrastructure\Persistence\Models\Setting;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * `value` (T1.9) di-cast 'array' pada model — sebenarnya penyimpan JSON
 * generik untuk skalar apa pun (int/float/bool), bukan array struktural.
 * Ketujuh kunci TH1–TH5b numerik, satu (`price.block_below_cost`, TH5c)
 * boolean. Dua field terpisah kunci `value` yang sama, tampil bergantian
 * berdasar tipe data record saat ini — pola standar Filament untuk field
 * "polimorfik" tanpa perlu form berbeda per kunci.
 */
class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('Kunci')
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('description')
                    ->label('Keterangan')
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(2),
                Toggle::make('value')
                    ->label('Nilai (aktif/nonaktif)')
                    ->visible(fn (?Setting $record): bool => is_bool($record?->value))
                    ->dehydrated(fn (?Setting $record): bool => is_bool($record?->value)),
                TextInput::make('value')
                    ->label('Nilai')
                    ->numeric()
                    ->required()
                    ->helperText('Angka bulat untuk nominal Rupiah (mis. 100000), desimal untuk persentase (mis. 0.10 = 10%).')
                    ->visible(fn (?Setting $record): bool => ! is_bool($record?->value))
                    ->dehydrated(fn (?Setting $record): bool => ! is_bool($record?->value))
                    ->dehydrateStateUsing(fn (string $state): int|float => str_contains($state, '.') ? (float) $state : (int) $state),
            ]);
    }
}
