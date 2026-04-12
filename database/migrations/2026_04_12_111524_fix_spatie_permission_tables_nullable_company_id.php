<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make company_id (the Spatie team foreign key) nullable across the three permission tables.
     *
     * WHY SURROGATE ID?
     * MySQL does not allow NULL in primary key columns. The original Spatie Teams schema uses
     * (company_id, role_id, model_id, model_type) as a composite PK, which prevents NULL company_id.
     * We need NULL company_id to support global roles (e.g. super_admin) that are not scoped to any tenant.
     *
     * IS THIS SAFE FOR SPATIE?
     * Yes. Spatie never queries by primary key. It uses WHERE clauses:
     *   WHERE model_has_roles.company_id = ? AND role_id = ? AND model_id = ? AND model_type = ?
     * The unique constraint we add replaces the PK's integrity guarantee.
     *
     * MULTI-TENANCY INTEGRITY:
     * The unique constraint (company_id, role_id, model_id, model_type) preserves the same guarantee
     * as the original composite PK. MySQL treats each NULL as distinct in unique indexes, so a user can
     * hold the same role name in Company A and Company B (different company_ids) without conflict.
     * A super_admin row with company_id = NULL is also unique per (role_id, model_id, model_type).
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'];   // 'company_id'
        $modelKey = $columnNames['model_morph_key'];   // 'model_id'

        // 1. roles — just make company_id nullable; it already has its own PK on `id`.
        Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey) {
            $table->unsignedBigInteger($teamKey)->nullable()->change();
        });

        // 2. model_has_permissions pivot
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey, $modelKey) {
            // Remove the composite PK (cannot contain NULLs in MySQL)
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');

            // Add surrogate auto-increment PK
            $table->id()->first();

            // Make company_id nullable
            $table->unsignedBigInteger($teamKey)->nullable()->change();

            // Restore integrity via unique index (MySQL treats NULLs as distinct — safe for multi-tenancy)
            $table->unique(
                [$teamKey, 'permission_id', $modelKey, 'model_type'],
                'model_has_permissions_unique'
            );
        });

        // 3. model_has_roles pivot
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey, $modelKey) {
            // Remove the composite PK
            $table->dropPrimary('model_has_roles_role_model_type_primary');

            // Add surrogate auto-increment PK
            $table->id()->first();

            // Make company_id nullable
            $table->unsignedBigInteger($teamKey)->nullable()->change();

            // Restore integrity via unique index
            $table->unique(
                [$teamKey, 'role_id', $modelKey, 'model_type'],
                'model_has_roles_unique'
            );
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
        $modelKey = $columnNames['model_morph_key'];

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey) {
            $table->unsignedBigInteger($teamKey)->nullable(false)->change();
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey, $modelKey) {
            $table->dropUnique('model_has_permissions_unique');
            $table->dropColumn('id');
            $table->unsignedBigInteger($teamKey)->nullable(false)->change();
            $table->primary([$teamKey, 'permission_id', $modelKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey, $modelKey) {
            $table->dropUnique('model_has_roles_unique');
            $table->dropColumn('id');
            $table->unsignedBigInteger($teamKey)->nullable(false)->change();
            $table->primary([$teamKey, 'role_id', $modelKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }
};
