<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashEntries\Schemas;

use App\Domain\Finance\Enums\CashEntryType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CashEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i:s'),
                        TextEntry::make('branch.name')->label('Cabang'),
                        TextEntry::make('entry_type')
                            ->label('Jenis')
                            ->badge()
                            ->formatStateUsing(fn (CashEntryType $state): string => $state->label()),
                        TextEntry::make('amount')
                            ->label('Jumlah')
                            ->money('IDR')
                            ->color(fn (string $state): string => (float) $state >= 0 ? 'success' : 'danger'),
                        TextEntry::make('reference_type')
                            ->label('Jenis Dokumen')
                            ->formatStateUsing(fn (string $state): string => class_basename($state)),
                        TextEntry::make('reference_id')->label('ID Dokumen')->copyable(),
                        TextEntry::make('creator.name')->label('Dicatat oleh')->placeholder('—'),
                    ]),
            ]);
    }
}
