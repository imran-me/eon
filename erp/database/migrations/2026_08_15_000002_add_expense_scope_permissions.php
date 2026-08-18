<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two permissions the expense desk needs before employees can file their own claims.
 *
 * `view company expense` turns the existing two visibility tiers into three:
 *
 *     view all expense      → every company
 *     view company expense  → their own company        ← new middle tier
 *     neither               → only the rows they filed  ← new floor
 *
 * The floor is what an employee gets, and it is the DEFAULT — a role nobody
 * remembered to grant the middle tier to sees too little, never too much. That
 * direction is deliberate: the failure mode of a forgotten grant should be a
 * confused accountant, not a junior seeing the whole group's spending.
 *
 * `pay expense from bank` keeps the Bank Account card off the entry form for
 * staff who have no business spending from a company account. They can still
 * file against a float they hold, and (once the claim feature lands) out of their
 * own pocket.
 *
 * Both go to everyone who holds the matching expense permission today EXCEPT
 * `employee`, so nobody's current access narrows on deploy.
 */
return new class extends Migration
{
    /** Roles that keep company-wide sight and may spend from a bank. */
    private const GRANT_TO = ['super admin', 'admin', 'accountant', 'Operation', 'Travels Accountant'];

    private const PERMISSIONS = ['view company expense', 'pay expense from bank'];

    public function up(): void
    {
        // Match the guard the expense permissions already use rather than assuming
        // 'web'. A permission created under the wrong guard is invisible to can()
        // and silently denies everyone.
        $guard = DB::table('permissions')->where('name', 'view expense')->value('guard_name') ?: 'web';

        foreach (self::PERMISSIONS as $name) {
            $permissionId = DB::table('permissions')
                ->where('name', $name)->where('guard_name', $guard)->value('id');

            if (!$permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name'       => $name,
                    'guard_name' => $guard,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $roleIds = DB::table('roles')->whereIn('name', self::GRANT_TO)->pluck('id');

            foreach ($roleIds as $roleId) {
                // insertOrIgnore: rerunning must not trip the composite primary key.
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id'       => $roleId,
                ]);
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    /**
     * Spatie caches the permission map, so without this the new rows exist in the
     * database and every can() call still answers from the old copy.
     */
    private function forgetPermissionCache(): void
    {
        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
