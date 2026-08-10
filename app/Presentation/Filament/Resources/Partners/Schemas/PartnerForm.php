<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Partners\Schemas;

use App\Domain\Shared\Enums\PartnerType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Opsi Select `partner_type` dibangun manual dari `PartnerType::cases()`
 * di sini (lapisan Presentation), BUKAN dengan implement
 * `Filament\Support\Contracts\HasLabel` pada enum itu sendiri — enum
 * berada di `App\Domain\Shared\Enums`, dan lapisan Domain dilarang
 * bergantung pada Filament (`tests/Arch/LayeringTest.php`).
 */
class PartnerForm
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
                    ->label('Nama')
                    ->required()
                    ->maxLength(150),
                Select::make('partner_type')
                    ->label('Jenis Mitra')
                    ->options(collect(PartnerType::cases())->mapWithKeys(
                        fn (PartnerType $type): array => [$type->value => $type->label()]
                    ))
                    ->required(),
                TextInput::make('tax_id')
                    ->label('NPWP')
                    ->maxLength(30),
                TextInput::make('phone')
                    ->label('Telepon')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(150),
                TextInput::make('contact_person')
                    ->label('Contact Person')
                    ->maxLength(150),
                TextInput::make('city')
                    ->label('Kota')
                    ->maxLength(100),
                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('credit_limit')
                    ->label('Limit Kredit')
                    ->numeric()
                    ->prefix('Rp'),
                TextInput::make('payment_terms_days')
                    ->label('Termin Pembayaran (hari)')
                    ->numeric()
                    ->minValue(0),
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
