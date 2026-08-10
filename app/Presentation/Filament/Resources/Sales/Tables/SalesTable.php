<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Sales\Tables;

use App\Application\Actions\ApproveSaleDiscountAction;
use App\Application\Actions\FinalizeSaleAction;
use App\Application\Actions\VoidSaleAction;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesTable
{
    private static function hasPendingApproval(Sale $record): bool
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
                    ->label('Pelanggan')
                    ->placeholder('Walk-in'),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DocumentState $state): string => $state->label())
                    ->color(fn (DocumentState $state): string => match ($state) {
                        DocumentState::Draft => 'gray',
                        DocumentState::Final => 'success',
                        DocumentState::Void => 'danger',
                    }),
                TextColumn::make('items_count')
                    ->label('Baris')
                    ->counts('items'),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR'),
                TextColumn::make('payment_status')
                    ->label('Pelunasan')
                    ->badge()
                    ->formatStateUsing(fn (?PaymentStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Paid => 'success',
                        PaymentStatus::Partial => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('balance_due')
                    ->label('Sisa Tagihan')
                    ->money('IDR')
                    ->placeholder('—')
                    ->color(fn (?string $state): ?string => $state !== null && (float) $state > 0 ? 'danger' : null),
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
                    ->visible(fn (Sale $record): bool => $record->state === DocumentState::Draft && ! self::hasPendingApproval($record)),
                DeleteAction::make()
                    ->visible(fn (Sale $record): bool => $record->state === DocumentState::Draft && ! self::hasPendingApproval($record)),
                Action::make('finalize')
                    ->label('Finalisasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Sale $record): bool => ! self::hasPendingApproval($record)
                        && (auth()->user()?->can('finalize', $record) ?? false))
                    ->action(function (Sale $record) {
                        try {
                            $result = app(FinalizeSaleAction::class)->execute($record);
                        } catch (SaleValidationException $e) {
                            Notification::make()->title('Tidak dapat difinalisasi')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        if ($result->state === DocumentState::Draft) {
                            Notification::make()
                                ->title('Menunggu approval')
                                ->body('Diskon melebihi ambang — menunggu keputusan Admin/Owner (TH1/TH2).')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()->title('Penjualan difinalisasi.')->success()->send();
                    }),
                Action::make('approve')
                    ->label('Setujui Diskon')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Sale $record): bool => self::hasPendingApproval($record)
                        && (auth()->user()?->can('approve', $record) ?? false))
                    ->action(function (Sale $record) {
                        try {
                            app(ApproveSaleDiscountAction::class)->execute($record);
                        } catch (SaleValidationException $e) {
                            Notification::make()->title('Tidak dapat difinalisasi')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Diskon disetujui, penjualan difinalisasi.')->success()->send();
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Sale $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function (Sale $record, array $data) {
                        app(VoidSaleAction::class)->execute($record, $data['reason']);
                        Notification::make()->title('Penjualan dibatalkan.')->success()->send();
                    }),
            ]);
    }
}
