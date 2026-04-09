<?php

namespace Database\Seeders;

use App\Enums\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionsList = Arr::flatten(config('company_permissions.permissions', []));
        $guardName = 'web';
//        $superAdminName = Roles::SUPER_ADMIN->value;

        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if ($this->command) {
            $this->command->info('Starting Role & Permission synchronization (Seeder)...');
        }

        // 1. Prepare Upsert Data
        $upsertData = $this->prepareUpsertData($permissionsList, $guardName);

        // 2. Perform Upsert
        $this->performUpsert($upsertData, $guardName, $permissionsList);

        // 3. Ensure a Super Admin role exists and has all permissions
//        $superAdmin = Role::firstOrCreate([
//            'name' => $superAdminName,
//            'guard_name' => $guardName, // todo: change it to admin later
//        ]);
//
//        $superAdmin->syncPermissions(Permission::where('guard_name', $guardName)->get());

    }

    private function prepareUpsertData(array $permissionsList, string $guardName): array
    {
        $now = now();
        $upsertData = [];
        foreach ($permissionsList as $permission) {
            $upsertData[] = [
                'name' => $permission,
                'guard_name' => $guardName,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $upsertData;
    }

    private function performUpsert(array $upsertData, string $guardName, array $permissionsList): void
    {
        $tableName = config('permission.table_names.permissions');
        DB::table($tableName)->upsert(
            $upsertData,
            ['name', 'guard_name'],
            ['updated_at']
        );

        if ($this->command) {
            $this->command->info('Upserted '.count($upsertData).' permissions from config.');
        }

        $deletedCount = DB::table($tableName)
            ->where('guard_name', $guardName)
            ->whereNotIn('name', $permissionsList)
            ->delete();

        if ($deletedCount > 0 && $this->command) {
            $this->command->warn('Deleted '.$deletedCount.' stale permissions from the database.');
        }
    }
}

