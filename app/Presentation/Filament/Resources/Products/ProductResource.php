<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Products;

use App\Infrastructure\Persistence\Models\Product;
use App\Presentation\Filament\Resources\Products\Pages\CreateProduct;
use App\Presentation\Filament\Resources\Products\Pages\EditProduct;
use App\Presentation\Filament\Resources\Products\Pages\ListProducts;
use App\Presentation\Filament\Resources\Products\Schemas\ProductForm;
use App\Presentation\Filament\Resources\Products\Tables\ProductsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T2.9 — Produk. `products` REPLICATED (CLAUDE.md §7); `ProductPolicy`
 * (T2.5) menegakkan HQ-only write.
 *
 * §16 peringatan #5 — form/tabel ini TIDAK menampilkan field harga beli
 * apa pun (tidak ada kolomnya di model — lihat migration T2.5). Approval
 * TH5a-c untuk perubahan `selling_price` di atas ambang BELUM ditegakkan
 * di sini — gap yang sama seperti didokumentasikan di `ProductPolicy`,
 * bukan terlewat tanpa sengaja.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Produk';

    protected static ?string $modelLabel = 'Produk';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
