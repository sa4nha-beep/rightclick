<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseOrders\Tables;

use App\Application\Actions\ApprovePurchaseOrderAction;
use App\Application\Actions\FinalizePurchaseOrderAction;
use App\Application\Actions\VoidPurchaseOrderAction;
use App\Domain\Procurement\Exceptions\PurchaseOrderValidationException;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    private static function hasPendingApproval(PurchaseOrder $record): bool
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
                TextColumn::make('partner.name')
                    ->label('Pemasok'),
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
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR'),
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
                    ->visible(fn (PurchaseOrder $record): bool => $record->state === DocumentState::Draft && ! self::hasPendingApproval($record)),
                DeleteAction::make()
                    ->visible(fn (PurchaseOrder $record): bool => $record->state === DocumentState::Draft && ! self::hasPendingApproval($record)),
                Action::make('finalize')
                    ->label('Finalisasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record): bool => ! self::hasPendingApproval($record)
                        && (auth()->user()?->can('finalize', $record) ?? false))
                    ->action(function (PurchaseOrder $record) {
                        try {
                            $result = app(FinalizePurchaseOrderAction::class)->execute($record);
                        } catch (PurchaseOrderValidationException $e) {
                            Notification::make()->title('Tidak dapat difinalisasi')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        if ($result->state === DocumentState::Draft) {
                            Notification::make()
                                ->title('Menunggu approval')
                                ->body('Total PO melebihi ambang — menunggu keputusan Owner/Admin (TH4).')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()->title('Purchase order difinalisasi.')->success()->send();
                    }),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record): bool => self::hasPendingApproval($record)
                        && (auth()->user()?->can('approve', $record) ?? false))
                    ->action(function (PurchaseOrder $record) {
                        app(ApprovePurchaseOrderAction::class)->execute($record);
                        Notification::make()->title('Purchase order disetujui dan difinalisasi.')->success()->send();
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function (PurchaseOrder $record, array $data) {
                        app(VoidPurchaseOrderAction::class)->execute($record, $data['reason']);
                        Notification::make()->title('Purchase order dibatalkan.')->success()->send();
                    }),
            ]);
    }
}
