<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\AuditLogs\Schemas;

use App\Domain\Shared\Enums\AuditAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ringkasan')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Waktu')
                                    ->dateTime('d M Y H:i:s'),
                                TextEntry::make('actor.name')
                                    ->label('Aktor')
                                    ->placeholder('Sistem'),
                                TextEntry::make('action')
                                    ->label('Aksi')
                                    ->badge()
                                    ->color(fn (AuditAction $state): string => match ($state) {
                                        AuditAction::Created, AuditAction::Approved, AuditAction::Restored => 'success',
                                        AuditAction::Updated, AuditAction::Finalized => 'info',
                                        AuditAction::Deleted, AuditAction::SoftDeleted, AuditAction::Voided,
                                        AuditAction::Rejected, AuditAction::AccessDenied => 'danger',
                                    }),
                                TextEntry::make('model_type')
                                    ->label('Jenis Model')
                                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                                TextEntry::make('model_id')
                                    ->label('ID Record')
                                    ->copyable()
                                    ->fontFamily('mono'),
                                TextEntry::make('branch.name')
                                    ->label('Cabang')
                                    ->placeholder('—'),
                            ]),
                    ]),
                Section::make('Nilai Sebelum')
                    ->schema([
                        KeyValueEntry::make('old_values')
                            ->label('')
                            ->hiddenLabel(),
                    ])
                    ->visible(fn ($record): bool => filled($record->old_values)),
                Section::make('Nilai Sesudah')
                    ->schema([
                        KeyValueEntry::make('new_values')
                            ->label('')
                            ->hiddenLabel(),
                    ])
                    ->visible(fn ($record): bool => filled($record->new_values)),
                Section::make('Konteks Tambahan')
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->label('')
                            ->hiddenLabel(),
                    ])
                    ->visible(fn ($record): bool => filled($record->metadata)),
            ]);
    }
}
