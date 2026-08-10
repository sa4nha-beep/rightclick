<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode Cabang')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama Cabang')
                    ->required()
                    ->maxLength(150),
                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(3),
                TextInput::make('pic_name')
                    ->label('Nama PIC')
                    ->maxLength(150),
                Toggle::make('is_hq')
                    ->label('Node HQ'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
