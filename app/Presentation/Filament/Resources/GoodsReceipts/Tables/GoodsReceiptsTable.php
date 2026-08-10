<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\GoodsReceipts\Tables;

use App\Application\Actions\FinalizeGoodsReceiptAction;
use App\Application\Actions\VoidGoodsReceiptAction;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * TANPA `hasPendingApproval()`/action `approve` — beda dari
 * `PurchaseOrdersTable`, goods receipt tidak punya alur ambang (§10 tidak
 * menetapkan TH untuk goods receipt).
 */
class GoodsReceiptsTable
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
                TextColumn::make('partner.name')
                    ->label('Pemasok'),
                TextColumn::make('purchaseOrder.document_number')
                    ->label('PO')
                    ->placeholder('—'),
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
                    ->visible(fn (GoodsReceipt $record): bool => $record->state === DocumentState::Draft),
                DeleteAction::make()
                    ->visible(fn (GoodsReceipt $record): bool => $record->state === DocumentState::Draft),
                Action::make('finalize')
                    ->label('Finalisasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (GoodsReceipt $record): bool => auth()->user()?->can('finalize', $record) ?? false)
                    ->action(function (GoodsReceipt $record) {
                        try {
                            app(FinalizeGoodsReceiptAction::class)->execute($record);
                        } catch (StockDocumentValidationException $e) {
                            Notification::make()->title('Tidak dapat difinalisasi')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Penerimaan barang difinalisasi — stok bertambah.')->success()->send();
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (GoodsReceipt $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function (GoodsReceipt $record, array $data) {
                        try {
                            app(VoidGoodsReceiptAction::class)->execute($record, $data['reason']);
                        } catch (StockDocumentValidationException $e) {
                            Notification::make()->title('Tidak dapat dibatalkan')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Penerimaan barang dibatalkan.')->success()->send();
                    }),
            ]);
    }
}
