<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockTransfers\Schemas;

use App\Infrastructure\Persistence\Models\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * `branch_id` (cabang asal) TIDAK ada di form — terisi otomatis dari
 * `BranchContext` (`BelongsToBranch`), sama seperti dokumen lain. Hanya
 * `dest_branch_id` yang dipilih eksplisit.
 */
class StockTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('dest_branch_id')
                    ->label('Cabang Tujuan')
                    ->relationship('destBranch', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Repeater::make('lines')
                    ->relationship('lines')
                    ->label('Baris Barang')
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
                        TagsInput::make('serial_numbers')
                            ->label('Serial Number')
                            ->visible(function (Get $get): bool {
                                $product = Product::find($get('product_id'));

                                return $product !== null && $product->is_serialized;
                            })
                            ->helperText('Wajib diisi tepat sejumlah kuantitas (R3) — divalidasi saat pengiriman.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah Baris')
                    ->columnSpanFull(),
            ]);
    }
}
