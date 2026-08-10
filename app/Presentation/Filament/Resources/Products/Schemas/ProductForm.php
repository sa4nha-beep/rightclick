<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama Produk')
                    ->required()
                    ->maxLength(150),
                Select::make('product_category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('base_unit_id')
                    ->label('Satuan')
                    ->relationship('baseUnit', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('selling_price')
                    ->label('Harga Jual')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->prefix('Rp'),
                TextInput::make('reorder_level')
                    ->label('Batas Stok Minimum')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Kosongkan bila belum ada ambang peringatan stok untuk produk ini.'),
                Toggle::make('is_serialized')
                    ->label('Lacak Serial Number')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
