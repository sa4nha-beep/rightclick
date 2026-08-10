<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockTransfers\Tables;

use App\Application\Actions\DispatchStockTransferAction;
use App\Application\Actions\ReceiveStockTransferAction;
use App\Application\Actions\VoidStockTransferAction;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockTransfer;
use App\Infrastructure\Persistence\Models\StockTransferReceipt;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('No. Dokumen Kirim')
                    ->placeholder('— (draft)')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Cabang Asal'),
                TextColumn::make('destBranch.name')
                    ->label('Cabang Tujuan'),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DocumentState $state): string => $state->label())
                    ->color(fn (DocumentState $state): string => match ($state) {
                        DocumentState::Draft => 'gray',
                        DocumentState::Final => 'warning',
                        DocumentState::Void => 'danger',
                    }),
                IconColumn::make('receipt.id')
                    ->label('Sudah Diterima')
                    ->boolean()
                    ->getStateUsing(fn (StockTransfer $record): bool => $record->receipt()->where('state', 'final')->exists()),
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
                    ->visible(fn (StockTransfer $record): bool => $record->state === DocumentState::Draft),
                DeleteAction::make()
                    ->visible(fn (StockTransfer $record): bool => $record->state === DocumentState::Draft),
                Action::make('dispatch')
                    ->label('Kirim')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (StockTransfer $record): bool => auth()->user()?->can('dispatch', $record) ?? false)
                    ->action(function (StockTransfer $record) {
                        app(DispatchStockTransferAction::class)->execute($record);
                        Notification::make()->title('Transfer dikirim — stok cabang asal berkurang.')->success()->send();
                    }),
                Action::make('receive')
                    ->label('Terima')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (StockTransfer $record): bool => $record->state === DocumentState::Final
                        && ! $record->receipt()->exists()
                        && (auth()->user()?->can('create', StockTransferReceipt::class) ?? false))
                    ->action(function (StockTransfer $record) {
                        try {
                            app(ReceiveStockTransferAction::class)->execute($record);
                            Notification::make()->title('Transfer diterima — stok cabang tujuan bertambah.')->success()->send();
                        } catch (StockDocumentValidationException $exception) {
                            Notification::make()->title('Penerimaan ditolak')->body($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StockTransfer $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')->label('Alasan Pembatalan')->required(),
                    ])
                    ->action(function (StockTransfer $record, array $data) {
                        try {
                            app(VoidStockTransferAction::class)->execute($record, $data['reason']);
                            Notification::make()->title('Transfer dibatalkan.')->success()->send();
                        } catch (StockDocumentValidationException $exception) {
                            Notification::make()->title('Pembatalan ditolak')->body($exception->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
