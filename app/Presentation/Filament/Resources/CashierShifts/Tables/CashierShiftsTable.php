<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\CashierShifts\Tables;

use App\Application\Actions\CloseCashierShiftAction;
use App\Application\Actions\VoidCashierShiftAction;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\CashierShift;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CashierShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->placeholder('— (terbuka)')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Cabang'),
                TextColumn::make('cashier.name')
                    ->label('Kasir'),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DocumentState $state): string => $state->label())
                    ->color(fn (DocumentState $state): string => match ($state) {
                        DocumentState::Draft => 'gray',
                        DocumentState::Final => 'success',
                        DocumentState::Void => 'danger',
                    }),
                TextColumn::make('opening_cash')
                    ->label('Kas Awal')
                    ->money('IDR'),
                TextColumn::make('closing_cash_counted')
                    ->label('Kas Fisik')
                    ->money('IDR')
                    ->placeholder('—'),
                TextColumn::make('variance')
                    ->label('Selisih')
                    ->money('IDR')
                    ->placeholder('—')
                    ->color(fn (?string $state): ?string => $state !== null && (float) $state !== 0.0 ? 'danger' : null),
                TextColumn::make('opened_at')
                    ->label('Dibuka')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('opened_at', 'desc')
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
                    ->visible(fn (CashierShift $record): bool => $record->state === DocumentState::Draft),
                DeleteAction::make()
                    ->visible(fn (CashierShift $record): bool => $record->state === DocumentState::Draft),
                Action::make('close')
                    ->label('Tutup Shift')
                    ->icon('heroicon-o-lock-closed')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CashierShift $record): bool => auth()->user()?->can('close', $record) ?? false)
                    ->schema([
                        // Bagian AC-16 asli (HS-TASKS-RIGHTCLICK-v1.1 T4.2: "hitung
                        // per pecahan") — bukan lagi satu angka agregat. Bukan
                        // Repeater::relationship(): form ini ephemeral (aksi tabel,
                        // bukan halaman Resource penuh), baris disimpan lewat
                        // CloseCashierShiftAction::execute(), bukan langsung Eloquent.
                        Repeater::make('denomination_counts')
                            ->label('Hitung Kas per Pecahan')
                            ->schema([
                                TextInput::make('denomination')
                                    ->label('Pecahan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->minValue(0.01),
                                TextInput::make('quantity')
                                    ->label('Jumlah Lembar/Koin')
                                    ->numeric()
                                    ->integer()
                                    ->required()
                                    ->minValue(0)
                                    ->default(0),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->required(),
                    ])
                    ->action(function (CashierShift $record, array $data) {
                        $result = app(CloseCashierShiftAction::class)->execute($record, $data['denomination_counts']);

                        $variance = (float) $result->variance;

                        Notification::make()
                            ->title($variance === 0.0 ? 'Shift ditutup — kas sesuai.' : 'Shift ditutup — ada selisih kas.')
                            ->body($variance === 0.0 ? null : sprintf('Selisih: Rp%s', number_format($variance, 2, ',', '.')))
                            ->color($variance === 0.0 ? 'success' : 'warning')
                            ->send();
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CashierShift $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function (CashierShift $record, array $data) {
                        app(VoidCashierShiftAction::class)->execute($record, $data['reason']);
                        Notification::make()->title('Shift dibatalkan.')->success()->send();
                    }),
            ]);
    }
}
