<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Employees;

use App\Infrastructure\Persistence\Models\Employee;
use App\Presentation\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Presentation\Filament\Resources\Employees\Pages\EditEmployee;
use App\Presentation\Filament\Resources\Employees\Pages\ListEmployees;
use App\Presentation\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Presentation\Filament\Resources\Employees\Tables\EmployeesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T2.9 — Karyawan. `employees` REPLICATED (CLAUDE.md §7); `EmployeePolicy`
 * (T2.6) menegakkan HQ-only write, memakai permission `*_users`.
 */
class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Karyawan';

    protected static ?string $modelLabel = 'Karyawan';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
