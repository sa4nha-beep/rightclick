<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockOpnames\Tables;

use App\Application\Actions\FinalizeStockOpnameAction;
use App\Application\Actions\VoidStockOpnameAction;
use App\Domain\Inventory\Enums\StockOpnameType;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\StockOpname;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockOpnamesTable
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
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (StockOpnameType $state): string => $state->label()),
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
                    ->visible(fn (StockOpname $record): bool => $record->state === DocumentState::Draft),
                DeleteAction::make()
                    ->visible(fn (StockOpname $record): bool => $record->state === DocumentState::Draft),
                Action::make('finalize')
                    ->label('Finalisasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (StockOpname $record): bool => auth()->user()?->can('finalize', $record) ?? false)
                    ->action(function (StockOpname $record) {
                        try {
                            app(FinalizeStockOpnameAction::class)->execute($record);
                            Notification::make()->title('Opname difinalisasi.')->success()->send();
                        } catch (StockDocumentValidationException $exception) {
                            Notification::make()->title('Finalisasi ditolak')->body($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StockOpname $record): bool => auth()->user()?->can('void', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function (StockOpname $record, array $data) {
                        app(VoidStockOpnameAction::class)->execute($record, $data['reason']);
                        Notification::make()->title('Opname dibatalkan.')->success()->send();
                    }),
            ]);
    }
}
