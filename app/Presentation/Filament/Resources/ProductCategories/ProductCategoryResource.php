<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\ProductCategories;

use App\Infrastructure\Persistence\Models\ProductCategory;
use App\Presentation\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Presentation\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Presentation\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Presentation\Filament\Resources\ProductCategories\Schemas\ProductCategoryForm;
use App\Presentation\Filament\Resources\ProductCategories\Tables\ProductCategoriesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T2.9 — Kategori Produk. `product_categories` REPLICATED (CLAUDE.md §7);
 * `ProductCategoryPolicy` (T2.3) menegakkan HQ-only write, memakai
 * permission `*_products` (tidak ada grup permission terpisah).
 */
class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Kategori Produk';

    protected static ?string $modelLabel = 'Kategori Produk';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductCategories::route('/'),
            'create' => CreateProductCategory::route('/create'),
            'edit' => EditProductCategory::route('/{record}/edit'),
        ];
    }
}
