<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Membuat/mengedit draft purchase order (T5.1). `partner_id` TIDAK
 * difilter ke `PartnerType::Supplier`/`Both` di form — validasi tipe
 * pemasok ditegakkan `FinalizePurchaseOrderAction`, bukan di sini (sama
 * pola `SaleForm` yang juga tidak memfilter tipe partner).
 *
 * `unit_price` pada baris adalah harga PESANAN, bukan `unit_cost` batch —
 * lihat catatan `PurchaseOrderLine`.
 */
class PurchaseOrderForm
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
                Repeater::make('lines')
                    ->relationship('lines')
                    ->label('Baris Pesanan')
                    ->schema([
                        Select::make('product_id')
                            ->label('Produk')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Kuantitas')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001),
                        TextInput::make('unit_price')
                            ->label('Harga Pesanan')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah Baris')
                    ->columnSpanFull(),
            ]);
    }
}
