<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\SaleReturns\Tables;

use App\Application\Actions\FinalizeSaleReturnAction;
use App\Application\Actions\VoidSaleReturnAction;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\SaleReturn;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SaleReturnsTable
{
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
                TextColumn::make('sale.document_number')
                    ->label('Penjualan Asal'),
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
                TextColumn::make('total_refund')
                    ->label('Total Kembali')
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
                    ->visible(fn (SaleReturn $record): bool => $record->state === DocumentState::Draft),
                DeleteAction::make()
                    ->visible(fn (SaleReturn $record): bool => $record->state === DocumentState::Draft),
                Action::make('finalize')
                    ->label('Proses Retur')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SaleReturn $record): bool => auth()->user()?->can('finalize', $record) ?? false)
                    ->action(function (SaleReturn $record) {
                        try {
                            app(FinalizeSaleReturnAction::class)->execute($record);
                        } catch (SaleValidationException $e) {
                            Notification::make()->title('Tidak dapat diproses')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Retur diproses — stok dikembalikan.')->success()->send();
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SaleReturn $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function (SaleReturn $record, array $data) {
                        app(VoidSaleReturnAction::class)->execute($record, $data['reason']);
                        Notification::make()->title('Retur dibatalkan.')->success()->send();
                    }),
            ]);
    }
}
