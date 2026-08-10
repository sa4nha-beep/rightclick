<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(150),
                TextInput::make('id_number')
                    ->label('No. KTP/SIM')
                    ->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->helperText('Boleh dikosongkan bila dokumen identitas belum tersedia.'),
                Select::make('user_id')
                    ->label('Akun Login')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan bila karyawan ini tidak punya akun login sistem.'),
                TextInput::make('position')
                    ->label('Jabatan')
                    ->required()
                    ->maxLength(100),
                TextInput::make('department')
                    ->label('Departemen')
                    ->maxLength(100),
                TextInput::make('phone')
                    ->label('Telepon')
                    ->tel()
                    ->maxLength(30),
                DatePicker::make('date_of_birth')
                    ->label('Tanggal Lahir'),
                DatePicker::make('hired_at')
                    ->label('Tanggal Bergabung'),
                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
