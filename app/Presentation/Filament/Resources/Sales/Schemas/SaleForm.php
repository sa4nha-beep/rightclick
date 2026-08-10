<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Sales\Schemas;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Shared\Enums\DocumentState;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Service;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Membuat/mengedit draft penjualan (T4.1) — back-office fallback sebelum
 * POS Livewire (T4.4) ada. `cashier_shift_id` HANYA menawarkan shift
 * `draft` milik pengguna login — mencegah melampirkan penjualan ke shift
 * yang sudah tertutup (`FinalizeSaleAction` menolaknya juga, ini hanya
 * mempersempit pilihan di form).
 *
 * Baris (`items`) memakai pseudo-field `sellable_type` (tidak disimpan,
 * `dehydrated(false)`) untuk menentukan apakah baris berupa Produk atau
 * Jasa — TIDAK ADA abstraksi "sellable" bersama di model (lihat catatan
 * migration `sale_items`), jadi form mengelola dua Select terpisah dan
 * saling menonaktifkan lewat visibility, bukan satu Select morph.
 * `unit_price` terisi otomatis dari harga master saat produk/jasa dipilih,
 * tetap bisa diubah manual (mis. harga nego) — `FinalizeSaleAction` memakai
 * nilai yang tersimpan di baris, bukan membaca ulang master data.
 */
class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cashier_shift_id')
                    ->label('Shift Kasir')
                    ->options(fn () => CashierShift::query()
                        ->where('cashier_id', Auth::id())
                        ->where('state', DocumentState::Draft)
                        ->pluck('document_number', 'id')
                        ->map(fn ($number, $id) => $number ?? "Shift terbuka ({$id})"))
                    ->required()
                    ->helperText('Hanya shift terbuka milik Anda yang ditawarkan.'),
                Select::make('partner_id')
                    ->label('Pelanggan')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan untuk pelanggan walk-in.'),
                TextInput::make('discount_amount')
                    ->label('Diskon Total')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->minValue(0),
                Repeater::make('items')
                    ->relationship('items')
                    ->label('Baris Penjualan')
                    ->schema([
                        Select::make('sellable_type')
                            ->label('Jenis')
                            ->options(['product' => 'Produk', 'service' => 'Jasa'])
                            ->dehydrated(false)
                            ->live()
                            ->default(fn (Get $get) => $get('service_id') !== null ? 'service' : 'product')
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state === 'product') {
                                    $set('service_id', null);
                                } elseif ($state === 'service') {
                                    $set('product_id', null);
                                }
                            }),
                        Select::make('product_id')
                            ->label('Produk')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(fn (Get $get): bool => $get('sellable_type') !== 'service')
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $set('unit_price', $state !== null ? (string) Product::find($state)?->selling_price : null);
                            }),
                        Select::make('service_id')
                            ->label('Jasa')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(fn (Get $get): bool => $get('sellable_type') === 'service')
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $set('unit_price', $state !== null ? (string) Service::find($state)?->price : null);
                            }),
                        TextInput::make('quantity')
                            ->label('Kuantitas')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0.0001),
                        TextInput::make('unit_price')
                            ->label('Harga Satuan')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0),
                    ])
                    ->columns(5)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah Baris')
                    ->columnSpanFull(),
                Repeater::make('payments')
                    ->relationship('payments')
                    ->label('Pembayaran')
                    ->schema([
                        Select::make('method')
                            ->label('Metode')
                            ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                            ->required(),
                        TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0.01),
                        TextInput::make('reference_no')
                            ->label('No. Referensi')
                            ->maxLength(100),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah Pembayaran')
                    ->helperText('Boleh kurang dari total (DP) HANYA bila Pelanggan diisi — walk-in wajib lunas.')
                    ->columnSpanFull(),
            ]);
    }
}
