<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Payables;

use App\Infrastructure\Persistence\Models\Payable;
use App\Presentation\Filament\Resources\Payables\Pages\ListPayables;
use App\Presentation\Filament\Resources\Payables\Tables\PayablesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Sisi AP dari `ReceivableResource` — treatment simetris penuh (lihat
 * docblocknya untuk alasan desain lengkap).
 */
class PayableResource extends Resource
{
    protected static ?string $model = Payable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static ?string $navigationLabel = 'Hutang';

    protected static ?string $modelLabel = 'Hutang';

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    public static function table(Table $table): Table
    {
        return PayablesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayables::route('/'),
        ];
    }
}
