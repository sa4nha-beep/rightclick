<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sisi AP dari `receivable_payment_allocations` (lihat docblock
     * migration itu untuk alasan desain lengkap — treatment simetris
     * penuh). Menaut ke `purchase_payments` (header, bukan `cash_entries`).
     */
    public function up(): void
    {
        Schema::create('purchase_payment_allocations', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('purchase_payment_id')->constrained('purchase_payments')->restrictOnDelete();
            $table->foreignUuid('payable_id')->constrained('payables')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestampsTz();

            $table->index('purchase_payment_id');
            $table->index('payable_id');
        });

        DB::statement(
            'ALTER TABLE purchase_payment_allocations ADD CONSTRAINT purchase_payment_allocations_amount_positive_check '.
            'CHECK (amount > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payment_allocations');
    }
};
