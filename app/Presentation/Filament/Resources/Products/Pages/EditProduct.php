<?php

declare(strict_types=1);

namespace App\Presentation\Filament\Resources\Products\Pages;

use App\Application\Actions\ChangeProductSellingPriceAction;
use App\Infrastructure\Persistence\Models\Approval;
use App\Infrastructure\Persistence\Models\Product;
use App\Presentation\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * `handleRecordUpdate()` (penutupan PT16) — override WAJIB, bukan opsional.
 * Filament bawaan hanya memanggil `$record->update($data)` langsung, yang
 * akan menulis `selling_price` yang diajukan LANGSUNG ke database sebelum
 * TH5a/TH5b/TH5c sempat dicek — sama sekali menghindari gerbang approval.
 * Field lain (nama, SKU, kategori, dst.) tetap diterapkan LANGSUNG lewat
 * `$record->update()` seperti biasa — hanya `selling_price` yang dialihkan
 * ke `ChangeProductSellingPriceAction`, dan hanya bila NILAINYA benar-benar
 * berubah (membuka form tanpa mengubah harga tidak boleh memicu apa pun).
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Product);

        if (! array_key_exists('selling_price', $data)) {
            $record->update($data);

            return $record;
        }

        $newSellingPrice = (string) $data['selling_price'];
        $priceUnchanged = bccomp((string) $record->selling_price, $newSellingPrice, 2) === 0;
        unset($data['selling_price']);

        // Transaksi tunggal (bukan fill+save lalu Action terpisah tanpa
        // pembungkus) — bila ChangeProductSellingPriceAction menolak aktor
        // (AuthorizationException, permission manage_product_prices beda
        // dari edit_products), perubahan field LAIN yang sudah di-fill()
        // ikut di-rollback, bukan tersimpan sebagian.
        return DB::transaction(function () use ($record, $data, $newSellingPrice, $priceUnchanged) {
            $record->fill($data)->save();

            if ($priceUnchanged) {
                return $record;
            }

            $result = app(ChangeProductSellingPriceAction::class)->execute($record, $newSellingPrice);

            if ($result instanceof Approval) {
                Notification::make()
                    ->title('Menunggu approval')
                    ->body('Perubahan harga melebihi ambang — menunggu keputusan Owner/Admin (TH5a/TH5b/TH5c). Harga LAMA tetap berlaku sampai disetujui.')
                    ->warning()
                    ->send();

                return $record->refresh();
            }

            return $result;
        });
    }
}
