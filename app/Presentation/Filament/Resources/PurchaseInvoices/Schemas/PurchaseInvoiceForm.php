<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseInvoices\Schemas;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\GoodsReceipt;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Membuat/mengedit draft faktur pembelian (T5.2). `goods_receipt_id`
 * ditawarkan HANYA dari penerimaan yang sudah FINAL dan belum punya
 * faktur (`doesntHave('purchaseInvoice')`) — 1:1, `unique(goods_receipt_id)`
 * di database. `partner_id` terisi otomatis dari penerimaan yang dipilih
 * (disalin, bukan live join — lihat catatan migration), tetap bisa diubah
 * manual bila perlu.
 *
 * `total_amount` TIDAK ada di form — dikunci `FinalizePurchaseInvoiceAction`
 * dari `goods_receipts.total_amount`, bukan input pengguna.
 */
class PurchaseInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('goods_receipt_id')
                    ->label('Penerimaan Barang')
                    ->relationship(
                        'goodsReceipt',
                        'document_number',
                        fn ($query) => $query->where('state', DocumentState::Final->value)->doesntHave('purchaseInvoice'),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $set('partner_id', $state !== null ? GoodsReceipt::find($state)?->partner_id : null);
                    }),
                Select::make('partner_id')
                    ->label('Pemasok')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('invoice_number')
                    ->label('Nomor Faktur Pemasok')
                    ->required()
                    ->maxLength(60),
                DatePicker::make('invoice_date')
                    ->label('Tanggal Faktur')
                    ->required()
                    ->default(now()),
            ]);
    }
}
