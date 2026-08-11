<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\AuditLogs\Tables;

use App\Domain\Shared\Enums\AuditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Hanya `ViewAction` — tanpa edit, tanpa hapus, tanpa aksi massal apa pun
 * (PT6, T1.13: "Owner tetap tidak bisa menghapus atau mengubah audit_logs").
 * Tidak ada `toolbarActions()`/`BulkActionGroup` sama sekali di sini secara
 * sengaja, bukan sekadar dikosongkan.
 */
class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('actor.name')
                    ->label('Aktor')
                    ->placeholder('Sistem')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn (AuditAction $state): string => match ($state) {
                        AuditAction::Created, AuditAction::Approved, AuditAction::Restored => 'success',
                        AuditAction::Updated, AuditAction::Finalized, AuditAction::Reprinted => 'info',
                        AuditAction::Deleted, AuditAction::SoftDeleted, AuditAction::Voided,
                        AuditAction::Rejected, AuditAction::AccessDenied => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->searchable(),
                TextColumn::make('model_id')
                    ->label('ID Record')
                    ->copyable()
                    ->limit(12)
                    ->fontFamily('mono'),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->placeholder('—')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->label('Aksi')
                    ->options(array_combine(
                        array_map(fn (AuditAction $case): string => $case->value, AuditAction::cases()),
                        array_map(fn (AuditAction $case): string => $case->name, AuditAction::cases()),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
