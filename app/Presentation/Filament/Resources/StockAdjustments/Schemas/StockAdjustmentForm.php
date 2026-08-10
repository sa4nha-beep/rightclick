<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockAdjustments\Schemas;

use App\Domain\Inventory\Enums\StockAdjustmentDirection;
use App\Infrastructure\Persistence\Models\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StockAdjustmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('lines')
                    ->relationship('lines')
                    ->label('Baris Penyesuaian')
                    ->schema([
                        Select::make('product_id')
                            ->label('Produk')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Select::make('direction')
                            ->label('Arah')
                            ->options([
                                StockAdjustmentDirection::Increase->value => StockAdjustmentDirection::Increase->label(),
                                StockAdjustmentDirection::Decrease->value => StockAdjustmentDirection::Decrease->label(),
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('quantity')
                            ->label('Kuantitas')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001),
                        TextInput::make('unit_cost')
                            ->label('Unit Cost')
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('Rp')
                            ->required(fn (Get $get): bool => $get('direction') === StockAdjustmentDirection::Increase->value)
                            ->helperText('Wajib untuk arah Naik. Arah Turun memakai biaya FIFO batch yang terpakai.'),
                        Textarea::make('reason')
                            ->label('Alasan')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),
                        TagsInput::make('serial_numbers')
                            ->label('Serial Number (bila arah Naik)')
                            ->visible(function (Get $get): bool {
                                $product = Product::find($get('product_id'));

                                return $product !== null && $product->is_serialized;
                            })
                            ->helperText('Wajib diisi tepat sejumlah kuantitas (R3) — divalidasi saat finalisasi.')
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah Baris')
                    ->columnSpanFull(),
            ]);
    }
}
