<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Branches;

use App\Infrastructure\Persistence\Models\Branch;
use App\Presentation\Filament\Resources\Branches\Pages\CreateBranch;
use App\Presentation\Filament\Resources\Branches\Pages\EditBranch;
use App\Presentation\Filament\Resources\Branches\Pages\ListBranches;
use App\Presentation\Filament\Resources\Branches\Schemas\BranchForm;
use App\Presentation\Filament\Resources\Branches\Tables\BranchesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T2.9 — Cabang. `branches` REPLICATED (CLAUDE.md §7); `BranchPolicy`
 * (T2.1, sudah ada sejak T1.4/T1.5) menegakkan HQ-only write lewat
 * `GuardsMasterDataWrites` — di node cabang, tombol Buat/Ubah/Hapus tidak
 * pernah muncul bagi peran mana pun meski permission-nya tersedia.
 */
class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Cabang';

    protected static ?string $modelLabel = 'Cabang';

    protected static ?string $pluralModelLabel = 'Cabang';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BranchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BranchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranches::route('/'),
            'create' => CreateBranch::route('/create'),
            'edit' => EditBranch::route('/{record}/edit'),
        ];
    }
}
