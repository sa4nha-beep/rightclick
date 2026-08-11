<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penutupan PT16 (TH5a/TH5b/TH5c) — `Approval` sejak T1.9 diasumsikan
     * dokumen `$approvable`-nya SENDIRI sudah menyimpan nilai yang diajukan
     * (pola draft Sale/StockAdjustment/PurchaseOrder: nilai baru sudah ada
     * di baris draft, approval hanya memutuskan boleh/tidak diterapkan).
     * `Product` TIDAK punya konsep draft — `products.selling_price` adalah
     * satu-satunya nilai yang dibaca LANGSUNG oleh POS/back-office untuk
     * transaksi nyata SAAT INI. Menulis harga baru langsung ke kolom itu
     * sebelum disetujui akan membuat harga di bawah HPP (TH5c) sudah aktif
     * dijual SEBELUM Owner sempat menolaknya — bertentangan dengan makna
     * "selalu approval". `payload` (jsonb, nullable) menyimpan nilai yang
     * DIAJUKAN secara terpisah dari dokumen aslinya, diterapkan HANYA saat
     * `ApproveProductPriceChangeAction` benar-benar menyetujuinya — desain
     * generik, dapat dipakai modul lain di masa depan yang punya kebutuhan
     * serupa, bukan kolom khusus Product.
     */
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->jsonb('payload')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
