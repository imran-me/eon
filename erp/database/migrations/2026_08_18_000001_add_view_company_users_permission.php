<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Adds the middle tier of User Management access.
 *
 * Before this, UserController knew only "can you open the screen"
 * (`view users`) and never read `view all users`, so anyone granted
 * `view users` could open every employee in all twelve companies — including
 * their salary ledger. The controller now enforces three tiers:
 *
 *   view all users      → every employee, every company   (super admin, admin)
 *   view company users  → every employee in own company   (this migration)
 *   neither             → own record only                 (employee)
 *
 * `hr` and `Operation` are the roles that hold `view users` without
 * `view all users` today, so without this they would drop to self-only the
 * moment the controller change deploys. `accountant` does not currently hold
 * `view users` at all — it is included so that granting it later behaves
 * sensibly rather than silently locking that role to its own record.
 *
 * Run this in the SAME deploy as the controller change, not after it.
 */
return new class extends Migration
{
    /** Roles that may see everyone in their own company, but no further. */
    private const ROLES = ['hr', 'Operation', 'accountant'];

    private const PERMISSION = 'view company users';

    public function up(): void
    {
        // firstOrCreate, not create: this migration must be safe to re-run on a
        // database where the permission was added by hand first.
        $permission = Permission::firstOrCreate([
            'name'       => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->first();

            // A missing role is not an error — role names differ between
            // installs, and failing here would abort the whole deploy.
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            $permission->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
