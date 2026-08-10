<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\PurchaseInvoices;

use App\Infrastructure\Persistence\Models\PurchaseInvoice;
use App\Presentation\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Presentation\Filament\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Presentation\Filament\Resources\PurchaseInvoices\Pages\ListPurchaseInvoices;
use App\Presentation\Filament\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceForm;
use App\Presentation\Filament\Resources\PurchaseInvoices\Tables\PurchaseInvoicesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * T5.6 — Faktur pembelian (T5.2/T5.3). Catatan hutang/AP formal, TIDAK
 * menyentuh ledger stok (itu urusan `GoodsReceiptResource`). Finalisasi/
 * void/pembayaran adalah Action khusus (`PurchaseInvoicesTable`) — TANPA
 * alur approval (§10 tidak menetapkan TH untuk faktur pembelian).
 */
class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Faktur Pembelian';

    protected static ?string $modelLabel = 'Faktur Pembelian';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    public static function form(Schema $schema): Schema
    {
        return PurchaseInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseInvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseInvoices::route('/'),
            'create' => CreatePurchaseInvoice::route('/create'),
            'edit' => EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }
}
