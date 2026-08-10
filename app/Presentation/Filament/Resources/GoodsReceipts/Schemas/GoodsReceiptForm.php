<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\GoodsReceipts\Schemas;

use App\Infrastructure\Persistence\Models\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Membuat/mengedit draft penerimaan barang (T5.2, simpul kritis).
 * `purchase_order_id` OPSIONAL — Gudang (pemegang `perform_goods_receipt`)
 * tidak punya `view_purchase_orders`, jadi field ini murni ketertelusuran,
 * tidak wajib (lihat catatan migration `goods_receipts`).
 *
 * `unit_cost` pada baris WAJIB SUDAH TERMASUK PPN (R2/AC-09) — diketik
 * apa adanya dari faktur pemasok yang menyertai barang secara fisik.
 */
class GoodsReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('partner_id')
                    ->label('Pemasok')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('purchase_order_id')
                    ->label('Purchase Order (opsional)')
                    ->relationship('purchaseOrder', 'document_number')
                    ->searchable()
                    ->preload()
                    ->helperText('Boleh dikosongkan untuk penerimaan tanpa PO formal.'),
                Repeater::make('lines')
                    ->relationship('lines')
                    ->label('Baris Penerimaan')
                    ->schema([
                        Select::make('product_id')
                            ->label('Produk')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Kuantitas')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001),
                        TextInput::make('unit_cost')
                            ->label('Unit Cost (termasuk PPN)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0)
                            ->helperText('Wajib nilai dari faktur pemasok TERMASUK PPN (R2/AC-09) — HAEN KOMPUTER non-PKP.'),
                        TagsInput::make('serial_numbers')
                            ->label('Serial Number')
                            ->visible(function (Get $get): bool {
                                $product = Product::find($get('product_id'));

                                return $product !== null && $product->is_serialized;
                            })
                            ->helperText('Wajib diisi tepat sejumlah kuantitas (R3) — divalidasi saat finalisasi.')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah Baris')
                    ->columnSpanFull(),
            ]);
    }
}
