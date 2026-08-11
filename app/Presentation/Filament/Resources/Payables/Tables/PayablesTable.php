<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Payables\Tables;

use App\Domain\Sales\Enums\PaymentStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('purchaseInvoice.document_number')
                    ->label('No. Faktur')
                    ->searchable(),
                TextColumn::make('partner.name')
                    ->label('Pemasok')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Cabang'),
                TextColumn::make('original_amount')
                    ->label('Hutang Awal')
                    ->money('IDR'),
                TextColumn::make('paid_amount')
                    ->label('Terbayar')
                    ->money('IDR'),
                TextColumn::make('outstanding_amount')
                    ->label('Sisa')
                    ->money('IDR')
                    ->color(fn (string $state): string => (float) $state > 0 ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Paid => 'success',
                        PaymentStatus::Partial => 'warning',
                        PaymentStatus::Unpaid => 'danger',
                    }),
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
            ]);
    }
}
