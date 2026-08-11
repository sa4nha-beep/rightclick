<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sisi AP dari migration `alter_receivable_payments_drop_sale_id` —
     * lihat docblocknya untuk alasan desain lengkap (treatment simetris).
     */
    public function up(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropForeign(['purchase_invoice_id']);
            $table->dropColumn('purchase_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreignUuid('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->restrictOnDelete();
        });
    }
};
