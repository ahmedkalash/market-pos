<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'];

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey) {
            // Drop old composite PK
            $table->dropPrimary([$teamKey, 'permission_id', 'model_id', 'model_type']);

            // Add surrogate ID as new PK
            $table->id()->first();

            // Make company_id nullable
            $table->unsignedBigInteger($teamKey)->nullable()->change();

            // Re-add uniqueness
            $table->unique([$teamKey, 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_unique');
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey) {
            // Drop old composite PK
            $table->dropPrimary([$teamKey, 'role_id', 'model_id', 'model_type']);

            // Add surrogate ID as new PK
            $table->id()->first();

            // Make company_id nullable
            $table->unsignedBigInteger($teamKey)->nullable()->change();

            // Re-add uniqueness
            $table->unique([$teamKey, 'role_id', 'model_id', 'model_type'], 'model_has_roles_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'];

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey) {
            $table->dropUnique('model_has_permissions_unique');
            $table->dropColumn('id');
            $table->unsignedBigInteger($teamKey)->nullable(false)->change();
            $table->primary([$teamKey, 'permission_id', 'model_id', 'model_type']);
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey) {
            $table->dropUnique('model_has_roles_unique');
            $table->dropColumn('id');
            $table->unsignedBigInteger($teamKey)->nullable(false)->change();
            $table->primary([$teamKey, 'role_id', 'model_id', 'model_type']);
        });
    }
};
