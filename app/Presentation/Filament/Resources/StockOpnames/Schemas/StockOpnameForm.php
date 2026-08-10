<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockOpnames\Schemas;

use App\Domain\Inventory\Enums\StockOpnameType;
use App\Infrastructure\Persistence\Models\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Opsi `type` dibangun manual (bukan `HasLabel` pada enum Domain) — sama
 * alasannya dengan `PartnerType` (T2.9): Domain tidak boleh bergantung
 * Filament (LayeringTest).
 *
 * `system_qty` TIDAK ada di form — nilai itu selalu dihitung ulang oleh
 * `FinalizeStockOpnameAction` saat finalisasi, bukan diisi manual (mencegah
 * manipulasi selisih dari sisi form).
 */
class StockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Jenis Opname')
                    ->options([
                        StockOpnameType::Periodic->value => StockOpnameType::Periodic->label(),
                        StockOpnameType::OpeningBalance->value => StockOpnameType::OpeningBalance->label(),
                    ])
                    ->default(StockOpnameType::Periodic->value)
                    ->required()
                    ->helperText('Saldo Awal (R9) memerlukan permission tambahan saat finalisasi dan wajib mengisi Unit Cost tiap baris.'),
                Repeater::make('lines')
                    ->relationship('lines')
                    ->label('Baris Hitung')
                    ->schema([
                        Select::make('product_id')
                            ->label('Produk')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        TextInput::make('counted_qty')
                            ->label('Qty Hasil Hitung Fisik')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        TextInput::make('unit_cost')
                            ->label('Unit Cost (bila naik)')
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('Rp')
                            ->helperText('Wajib untuk Saldo Awal, atau bila belum ada batch sebelumnya untuk produk ini.'),
                        Textarea::make('reason')
                            ->label('Alasan Selisih')
                            ->rows(2)
                            ->helperText('Wajib bila qty hasil hitung berbeda dari sistem (AC-12) — divalidasi saat finalisasi.')
                            ->columnSpanFull(),
                        TagsInput::make('serial_numbers')
                            ->label('Serial Number (bila selisih naik)')
                            ->visible(function (Get $get): bool {
                                $product = Product::find($get('product_id'));

                                return $product !== null && $product->is_serialized;
                            })
                            ->helperText('Wajib diisi tepat sejumlah selisih naik (R3) — divalidasi saat finalisasi.')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah Baris')
                    ->columnSpanFull(),
            ]);
    }
}
