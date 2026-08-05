<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RIGHTCLICK foundational identity tables (T1.4).
     * DB Design §4.1 — branches (REPLICATED), users (REPLICATED),
     * user_branches (REPLICATED).
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->string('code', 10)->unique();
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('pic_name', 150)->nullable();
            $table->boolean('is_hq')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->string('name', 150);
            $table->string('username', 50)->unique();
            $table->string('email', 150)->nullable();
            $table->string('password');
            $table->foreignUuid('default_branch_id')->constrained('branches')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('locally_disabled_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('user_branches', function (Blueprint $table) {
            $table->uuidPrimaryKey();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['user_id', 'branch_id']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('user_branches');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');
    }
};
