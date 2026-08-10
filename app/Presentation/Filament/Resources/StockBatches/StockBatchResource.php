<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockBatches;

use App\Infrastructure\Persistence\Models\StockBatch;
use App\Presentation\Filament\Resources\StockBatches\Pages\ListStockBatches;
use App\Presentation\Filament\Resources\StockBatches\Pages\ViewStockBatch;
use App\Presentation\Filament\Resources\StockBatches\Schemas\StockBatchInfolist;
use App\Presentation\Filament\Resources\StockBatches\Tables\StockBatchesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * T3.3 — batch stok (`StockBatchPolicy`, T3.1). TIDAK ADA halaman
 * create/edit — batch hanya lahir dari `StockLedgerService` (R1), dipicu
 * dokumen opname/adjustment/transfer (T3.4-T3.6).
 *
 * Kolom `unit_cost` disaring DI LAPISAN QUERY untuk peran tanpa
 * `view_stock_cost` (P6, §2 — Gudang "tidak melihat nilai") lewat
 * `getEloquentQuery()` di bawah — berlaku pada halaman List MAUPUN View
 * (keduanya menurunkan query dari sini), bukan sekadar disembunyikan di
 * tabel/infolist. `TextColumn`/`TextEntry` `unit_cost` juga dipasangi
 * `visible()` sebagai pertahanan berlapis di UI.
 */
class StockBatchResource extends Resource
{
    protected static ?string $model = StockBatch::class;

    private const COLUMNS_WITHOUT_COST = [
        'id', 'branch_id', 'product_id', 'received_at', 'qty_received',
        'qty_remaining', 'reference_type', 'reference_id',
        'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at',
    ];

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! Auth::user()?->can('view_stock_cost')) {
            $query->select(self::COLUMNS_WITHOUT_COST);
        }

        return $query;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCubeTransparent;

    protected static ?string $navigationLabel = 'Batch Stok';

    protected static ?string $modelLabel = 'Batch Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventaris';

    public static function infolist(Schema $schema): Schema
    {
        return StockBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockBatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockBatches::route('/'),
            'view' => ViewStockBatch::route('/{record}'),
        ];
    }
}
