<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\ProductCategories\Schemas;

use App\Infrastructure\Persistence\Models\ProductCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama Kategori')
                    ->required()
                    ->maxLength(150),
                Select::make('parent_id')
                    ->label('Kategori Induk')
                    ->relationship(
                        name: 'parent',
                        titleAttribute: 'name',
                        // Kategori tidak boleh menjadi induk dirinya sendiri.
                        modifyQueryUsing: fn (Builder $query, ?ProductCategory $record): Builder => $record !== null
                            ? $query->whereKeyNot($record->id)
                            : $query,
                    )
                    ->searchable()
                    ->preload(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
