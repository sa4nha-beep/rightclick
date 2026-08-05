<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RIGHTCLICK authorization tables (T1.5).
     * Adapted from spatie/laravel-permission to use UUID instead of bigIncrements.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('guard_name')->default('web');
            $table->timestampsTz();
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('guard_name')->default('web');
            $table->timestampsTz();
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->uuidMorphs('model');
            $table->foreignUuid('permission_id')->constrained(
                table: $tableNames['permissions'],
                indexName: 'model_has_permissions_permission_id_index'
            )->cascadeOnDelete();
            $table->unique(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_unique');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
            $table->uuidMorphs('model');
            $table->foreignUuid('role_id')->constrained(
                table: $tableNames['roles'],
                indexName: 'model_has_roles_role_id_index'
            )->cascadeOnDelete();
            $table->unique(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_unique');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->foreignUuid('permission_id')->constrained(
                table: $tableNames['permissions'],
                indexName: 'role_has_permissions_permission_id_index'
            )->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained(
                table: $tableNames['roles'],
                indexName: 'role_has_permissions_role_id_index'
            )->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
