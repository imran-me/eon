<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Who may hand money back to staff.
 *
 * Kept apart from `approve expense` on purpose, even though the same people hold
 * both today. Approving a claim says "yes, the company owes this"; paying it says
 * "and here is the cash". They are different acts on different days, and the day
 * someone wants a clerk who can record payments but not approve claims — or the
 * reverse — the permission is already there to split them on.
 */
return new class extends Migration
{
    private const PERMISSION = 'reimburse employee';

    /** The same four roles that already approve expenses, per the owner's decision. */
    private const GRANT_TO = ['super admin', 'admin', 'accountant', 'Operation'];

    public function up(): void
    {
        $guard = DB::table('permissions')->where('name', 'approve expense')->value('guard_name') ?: 'web';

        $id = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', $guard)->value('id');

        if (!$id) {
            $id = DB::table('permissions')->insertGetId([
                'name'       => self::PERMISSION,
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('roles')->whereIn('name', self::GRANT_TO)->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $id,
                'role_id'       => $roleId,
            ]);
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('name', self::PERMISSION)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
