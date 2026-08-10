<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\SaleReturns\Schemas;

use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\SaleItem;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Membuat/mengedit draft retur (T4.3). `sale_id` HANYA menawarkan penjualan
 * FINAL. Baris (`sale_item_id`) HANYA menawarkan baris PRODUK (bukan jasa)
 * milik penjualan yang dipilih — `$get('../../sale_id')` naik DUA level
 * dari konteks Select di dalam Repeater: satu keluar dari skema item
 * Repeater, satu lagi keluar dari Repeater itu sendiri, baru sampai ke
 * field header sepadan. Satu level (`../sale_id`) — pola yang dipakai
 * `StockAdjustmentForm` untuk field SEPADAN di baris yang sama — TIDAK
 * cukup di sini karena `sale_id` bukan sepadan (sibling) baris, melainkan
 * field HEADER di luar Repeater; ditemukan lewat percobaan langsung
 * (`$get('../sale_id')` diam-diam mengembalikan `null` tanpa error,
 * lolos validasi form biasa tapi gagal "in:" saat submit) — dicatat di
 * sini agar sesi berikutnya tidak mengulang jebakan yang sama.
 */
class SaleReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sale_id')
                    ->label('Penjualan')
                    ->options(fn () => Sale::query()
                        ->where('state', DocumentState::Final)
                        ->pluck('document_number', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->helperText('Hanya penjualan berstatus Final yang dapat diretur.'),
                Repeater::make('lines')
                    ->relationship('lines')
                    ->label('Baris Retur')
                    ->schema([
                        Select::make('sale_item_id')
                            ->label('Baris Penjualan')
                            ->options(function (Get $get) {
                                $saleId = $get('../../sale_id');

                                if ($saleId === null) {
                                    return [];
                                }

                                return SaleItem::query()
                                    ->where('sale_id', $saleId)
                                    ->whereNotNull('product_id')
                                    ->with('product')
                                    ->get()
                                    ->mapWithKeys(fn (SaleItem $item) => [
                                        $item->id => sprintf('%s (terjual %s)', $item->product->name, (string) $item->quantity),
                                    ]);
                            })
                            ->searchable()
                            ->live()
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Kuantitas Retur')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001),
                        Textarea::make('reason')
                            ->label('Alasan Retur')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),
                        TagsInput::make('serial_numbers')
                            ->label('Serial Number')
                            ->visible(function (Get $get): bool {
                                $saleItemId = $get('sale_item_id');

                                if ($saleItemId === null) {
                                    return false;
                                }

                                $product = SaleItem::find($saleItemId)?->product;

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
