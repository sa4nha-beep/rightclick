<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Products\Tables;

use App\Application\Actions\ApproveProductPriceChangeAction;
use App\Domain\Shared\Enums\ApprovalStatus;
use App\Domain\Shared\Exceptions\ApprovalException;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    /**
     * Pola sama `SalesTable::hasPendingApproval()` (T4.1) — permintaan
     * TH5a/TH5b/TH5c tertunda menampilkan tombol "Setujui Harga",
     * menyembunyikan `EditAction` biasa (harga BELUM final, jangan biarkan
     * diedit lagi di atas permintaan yang masih menggantung).
     */
    private static function hasPendingApproval(Product $record): bool
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
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable(),
                TextColumn::make('baseUnit.name')
                    ->label('Satuan'),
                TextColumn::make('selling_price')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable(),
                IconColumn::make('is_serialized')
                    ->label('Serial')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('product_category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Product $record): bool => ! self::hasPendingApproval($record)),
                Action::make('approveProductPrice')
                    ->label('Setujui Harga')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Product $record): bool => self::hasPendingApproval($record)
                        && (auth()->user()?->can('approve', $record) ?? false))
                    ->action(function (Product $record) {
                        $approval = Approval::query()
                            ->where('approvable_type', $record->getMorphClass())
                            ->where('approvable_id', $record->getKey())
                            ->where('status', ApprovalStatus::Pending)
                            ->latest('requested_at')
                            ->first();

                        if ($approval === null) {
                            Notification::make()->title('Tidak ada permintaan tertunda')->danger()->send();

                            return;
                        }

                        try {
                            app(ApproveProductPriceChangeAction::class)->execute($approval);
                        } catch (ApprovalException $e) {
                            Notification::make()->title('Tidak dapat disetujui')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Harga baru disetujui dan diterapkan.')->success()->send();
                    }),
                DeleteAction::make(),
            ]);
    }
}
