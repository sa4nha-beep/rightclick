<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\StockMutations;

use App\Infrastructure\Persistence\Models\StockMutation;
use App\Presentation\Filament\Resources\StockMutations\Pages\ListStockMutations;
use App\Presentation\Filament\Resources\StockMutations\Pages\ViewStockMutation;
use App\Presentation\Filament\Resources\StockMutations\Schemas\StockMutationInfolist;
use App\Presentation\Filament\Resources\StockMutations\Tables\StockMutationsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * T3.3 — ledger mutasi stok (`StockMutationPolicy`, T3.2, R1). TIDAK ADA
 * halaman create/edit — append-only, satu-satunya jalur tulis adalah
 * `StockLedgerService` (sama seperti `AuditLogResource`, T1.14).
 *
 * Kolom `unit_cost` disaring di lapisan query lewat `getEloquentQuery()`
 * (P6) — sama pola dengan `StockBatchResource`.
 */
class StockMutationResource extends Resource
{
    protected static ?string $model = StockMutation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Mutasi Stok';

    protected static ?string $modelLabel = 'Mutasi Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventaris';

    private const COLUMNS_WITHOUT_COST = [
        'id', 'branch_id', 'product_id', 'stock_batch_id', 'mutation_type',
        'quantity', 'reference_type', 'reference_id', 'occurred_at',
        'created_by', 'created_at',
    ];

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! Auth::user()?->can('view_stock_cost')) {
            $query->select(self::COLUMNS_WITHOUT_COST);
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockMutationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockMutationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMutations::route('/'),
            'view' => ViewStockMutation::route('/{record}'),
        ];
    }
}
