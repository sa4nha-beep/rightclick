<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockTransferReceipts\Tables;

use App\Application\Actions\VoidStockTransferReceiptAction;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockTransferReceipt;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only — receipt hanya lahir dari `ReceiveStockTransferAction`, tidak
 * ada create/edit di panel (sama pola dengan `AuditLogResource`, T1.14).
 */
class StockTransferReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('No. Dokumen Terima')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Cabang Tujuan'),
                TextColumn::make('stockTransfer.document_number')
                    ->label('Dokumen Kirim Asal'),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DocumentState $state): string => $state->label())
                    ->color(fn (DocumentState $state): string => match ($state) {
                        DocumentState::Draft => 'gray',
                        DocumentState::Final => 'success',
                        DocumentState::Void => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->label('Diterima')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label('Status')
                    ->options([
                        DocumentState::Final->value => DocumentState::Final->label(),
                        DocumentState::Void->value => DocumentState::Void->label(),
                    ]),
            ])
            ->recordActions([
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StockTransferReceipt $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')->label('Alasan Pembatalan')->required(),
                    ])
                    ->action(function (StockTransferReceipt $record, array $data) {
                        app(VoidStockTransferReceiptAction::class)->execute($record, $data['reason']);
                        Notification::make()->title('Penerimaan dibatalkan.')->success()->send();
                    }),
            ]);
    }
}
