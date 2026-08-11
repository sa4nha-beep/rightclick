<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseInvoices\Tables;

use App\Application\Actions\FinalizePurchaseInvoiceAction;
use App\Application\Actions\RecordPurchasePaymentAction;
use App\Application\Actions\VoidPurchaseInvoiceAction;
use App\Domain\Procurement\Exceptions\PurchaseInvoiceValidationException;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use App\Infrastructure\Persistence\Models\PurchasePayment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * TANPA `hasPendingApproval()`/action `approve` — sama alasan
 * `GoodsReceiptsTable`, tidak ada alur ambang untuk faktur pembelian.
 *
 * Action `pay` (T5.3, "Catat Pembayaran") — BEDA dari `finalize`/`void`:
 * tetap tersedia berkali-kali selama dokumen `final` dan sisa hutang > 0
 * (cicilan/parsial), bukan transisi status sekali jalan.
 */
class PurchaseInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->placeholder('— (draft)')
                    ->searchable(),
                TextColumn::make('invoice_number')
                    ->label('No. Faktur Pemasok')
                    ->searchable(),
                TextColumn::make('partner.name')
                    ->label('Pemasok'),
                TextColumn::make('goodsReceipt.document_number')
                    ->label('Penerimaan'),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DocumentState $state): string => $state->label())
                    ->color(fn (DocumentState $state): string => match ($state) {
                        DocumentState::Draft => 'gray',
                        DocumentState::Final => 'success',
                        DocumentState::Void => 'danger',
                    }),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR'),
                TextColumn::make('payment_status')
                    ->label('Pelunasan')
                    ->badge()
                    ->getStateUsing(fn (PurchaseInvoice $record): ?PaymentStatus => $record->state === DocumentState::Final ? $record->paymentStatus() : null)
                    ->formatStateUsing(fn (?PaymentStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Paid => 'success',
                        PaymentStatus::Partial => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('balance_due')
                    ->label('Sisa Hutang')
                    ->money('IDR')
                    ->placeholder('—')
                    ->getStateUsing(fn (PurchaseInvoice $record): ?string => $record->state === DocumentState::Final ? $record->balanceDue() : null)
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
                    ->visible(fn (PurchaseInvoice $record): bool => $record->state === DocumentState::Draft),
                DeleteAction::make()
                    ->visible(fn (PurchaseInvoice $record): bool => $record->state === DocumentState::Draft),
                Action::make('finalize')
                    ->label('Finalisasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseInvoice $record): bool => auth()->user()?->can('finalize', $record) ?? false)
                    ->action(function (PurchaseInvoice $record) {
                        try {
                            app(FinalizePurchaseInvoiceAction::class)->execute($record);
                        } catch (PurchaseInvoiceValidationException $e) {
                            Notification::make()->title('Tidak dapat difinalisasi')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Faktur pembelian difinalisasi.')->success()->send();
                    }),
                Action::make('pay')
                    ->label('Catat Pembayaran')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseInvoice $record): bool => $record->state === DocumentState::Final
                        && bccomp($record->balanceDue(), '0', 2) > 0
                        && (auth()->user()?->can('create', PurchasePayment::class) ?? false))
                    ->schema([
                        Select::make('method')
                            ->label('Metode')
                            ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                            ->required(),
                        TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0.01),
                        TextInput::make('reference_no')
                            ->label('No. Referensi')
                            ->maxLength(100),
                    ])
                    ->action(function (PurchaseInvoice $record, array $data) {
                        // Aksi ini SELALU membentuk SATU alokasi (ke payable milik
                        // faktur ini sendiri) — kapabilitas "satu pembayaran ke banyak
                        // faktur" (FR-M11a-05) ada di level Action, layar UI khusus
                        // untuk itu adalah task terpisah (lihat CLAUDE.md).
                        try {
                            app(RecordPurchasePaymentAction::class)->execute(
                                [['payable_id' => (string) $record->payable?->id, 'amount' => (string) $data['amount']]],
                                (string) $data['method'],
                                (string) $data['amount'],
                                $data['reference_no'] ?? null,
                            );
                        } catch (PurchaseInvoiceValidationException $e) {
                            Notification::make()->title('Pembayaran ditolak')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Pembayaran tercatat.')->success()->send();
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseInvoice $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function (PurchaseInvoice $record, array $data) {
                        try {
                            app(VoidPurchaseInvoiceAction::class)->execute($record, $data['reason']);
                        } catch (PurchaseInvoiceValidationException $e) {
                            Notification::make()->title('Tidak dapat dibatalkan')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Faktur pembelian dibatalkan.')->success()->send();
                    }),
            ]);
    }
}
