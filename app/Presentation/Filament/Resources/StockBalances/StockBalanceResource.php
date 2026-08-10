<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBalances;

use App\Infrastructure\Persistence\Models\StockBalance;
use App\Presentation\Filament\Resources\StockBalances\Pages\ListStockBalances;
use App\Presentation\Filament\Resources\StockBalances\Pages\ViewStockBalance;
use App\Presentation\Filament\Resources\StockBalances\Schemas\StockBalanceInfolist;
use App\Presentation\Filament\Resources\StockBalances\Tables\StockBalancesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T3.3 — status stok (kuantitas per cabang/produk). Cache turunan LOCAL
 * (`StockBalancePolicy`) — tidak ada halaman create/edit, hanya ditulis
 * `StockLedgerService` atau `php artisan stock:rebuild-balances`.
 *
 * Hanya kuantitas, TIDAK ADA `unit_cost` — permission `view_stock`, bukan
 * `view_batches`/`view_stock_mutations`, aman untuk Kasir (POS-05) dan
 * Gudang (§2 — melihat kuantitas, bukan nilai).
 */
class StockBalanceResource extends Resource
{
    protected static ?string $model = StockBalance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Status Stok';

    protected static ?string $modelLabel = 'Status Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventaris';

    public static function infolist(Schema $schema): Schema
    {
        return StockBalanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockBalancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockBalances::route('/'),
            'view' => ViewStockBalance::route('/{record}'),
        ];
    }
}
