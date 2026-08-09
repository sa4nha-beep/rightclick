<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\AuditLogs;

use App\Infrastructure\Persistence\Models\AuditLog;
use App\Presentation\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Presentation\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Presentation\Filament\Resources\AuditLogs\Schemas\AuditLogInfolist;
use App\Presentation\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T1.14 — halaman Audit Log (R11, P1, HS-PERM-RIGHTCLICK-v1.2 §3.1/§4.1).
 *
 * TIDAK ADA halaman create/edit terdaftar — audit_logs append-only, satu-
 * satunya jalur tulis adalah `AuditService`/trait `Auditable` (T1.6), bukan
 * lewat panel. Tabel dan halaman detail (`view`) murni baca. Otorisasi
 * (viewAny/view selalu false untuk create/update/delete) ditegakkan
 * `AuditLogPolicy` — Filament Resource menegakkannya otomatis lewat Policy,
 * tapi halaman create/edit sengaja tidak didaftarkan sama sekali sebagai
 * pertahanan berlapis (PT6 — "tidak ada tombol hapus di mana pun").
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $modelLabel = 'Audit Log';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    public static function infolist(Schema $schema): Schema
    {
        return AuditLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }
}
