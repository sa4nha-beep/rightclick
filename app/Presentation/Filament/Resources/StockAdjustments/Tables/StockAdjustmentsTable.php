<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockAdjustments\Tables;

use App\Application\Actions\ApproveStockAdjustmentAction;
use App\Application\Actions\FinalizeStockAdjustmentAction;
use App\Application\Actions\VoidStockAdjustmentAction;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\StockAdjustment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockAdjustmentsTable
{
    private static function hasPendingApproval(StockAdjustment $record): bool
    {
        return Approval::query()
            ->where('approvable_type', $record->getMorphClass())
            ->where('approvable_id', $record->getKey())
            ->where('status', ApprovalStatus::Pending)
            ->exists();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->placeholder('— (draft)')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Cabang'),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DocumentState $state): string => $state->label())
                    ->color(fn (DocumentState $state): string => match ($state) {
                        DocumentState::Draft => 'gray',
                        DocumentState::Final => 'success',
                        DocumentState::Void => 'danger',
                    }),
                TextColumn::make('lines_count')
                    ->label('Baris')
                    ->counts('lines'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label('Status')
                    ->options([
                        DocumentState::Draft->value => DocumentState::Draft->label(),
                        DocumentState::Final->value => DocumentState::Final->label(),
                        DocumentState::Void->value => DocumentState::Void->label(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (StockAdjustment $record): bool => $record->state === DocumentState::Draft && ! self::hasPendingApproval($record)),
                DeleteAction::make()
                    ->visible(fn (StockAdjustment $record): bool => $record->state === DocumentState::Draft && ! self::hasPendingApproval($record)),
                Action::make('finalize')
                    ->label('Finalisasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (StockAdjustment $record): bool => ! self::hasPendingApproval($record)
                        && (auth()->user()?->can('finalize', $record) ?? false))
                    ->action(function (StockAdjustment $record) {
                        $result = app(FinalizeStockAdjustmentAction::class)->execute($record);

                        if ($result->state === DocumentState::Draft) {
                            Notification::make()
                                ->title('Menunggu approval')
                                ->body('Nilai penyesuaian melebihi ambang — menunggu keputusan Owner/Admin (TH3/TH3b).')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()->title('Penyesuaian difinalisasi.')->success()->send();
                    }),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (StockAdjustment $record): bool => self::hasPendingApproval($record)
                        && (auth()->user()?->can('approve', $record) ?? false))
                    ->action(function (StockAdjustment $record) {
                        app(ApproveStockAdjustmentAction::class)->execute($record);
                        Notification::make()->title('Penyesuaian disetujui dan diterapkan.')->success()->send();
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StockAdjustment $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function (StockAdjustment $record, array $data) {
                        app(VoidStockAdjustmentAction::class)->execute($record, $data['reason']);
                        Notification::make()->title('Penyesuaian dibatalkan.')->success()->send();
                    }),
            ]);
    }
}
