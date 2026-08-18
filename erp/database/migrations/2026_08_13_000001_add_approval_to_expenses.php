<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval for expenses: nothing reaches the ledger until someone says so.
 *
 * WHAT WAS WRONG — `status` doubled as post/unpost. Setting it to 0 DELETED the
 * journal outright (ExpenseController::update()), so anyone holding `edit
 * expense` could take a posted expense off the books by changing a dropdown, and
 * a closed month could move with nothing left to show it had. Approval splits the
 * two apart: `status` stays the record's own active flag, and `approval_status`
 * alone decides whether the money is in the accounts.
 *
 * EVERY EXISTING ROW IS BACKFILLED TO 'approved'. They were already posted under
 * the old rules, and their journals already exist — leaving them 'pending' would
 * claim the ledger is holding money it is not, and the approve action would try
 * to write a second journal for each.
 *
 * THE PERMISSION IS GRANTED HERE, NOT LEFT TO THE ADMIN. A deploy that adds an
 * approval gate and grants it to nobody is an outage: every company's expenses
 * would file fine and silently never post. It goes to the roles that can already
 * `delete expense` — they could already erase a posting, so approving one is not
 * new power — plus `admin`, which holds `view all expense` and would otherwise be
 * unable to approve anything it can see.
 *
 * `Travels Accountant` is deliberately NOT granted it: that role can create and
 * edit but not delete, so it is the one role for which approval is a real
 * separation of duties. If that office has nobody else to approve for it, tick
 * the box on the Roles screen — that is a policy call, not a migration's.
 */
return new class extends Migration
{
    /** Roles that get `approve expense` on deploy. See the note above. */
    private const GRANT_TO = ['accountant', 'admin', 'Operation', 'super admin'];

    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Default 'pending' so anything filed AFTER this migration has to be
            // approved. Rows that already exist are corrected below.
            $table->enum('approval_status', ['pending', 'approved'])
                ->default('pending')
                ->after('status');

            $table->foreignId('approved_by')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            // The list screen filters on it and the index page counts pendings.
            $table->index(['company_id', 'approval_status'], 'expenses_company_approval_index');
        });

        // Everything already on the books is approved by definition — including
        // soft-deleted rows, so that restoring one does not resurrect it as an
        // unposted draft whose journal is already written.
        DB::table('expenses')->update(['approval_status' => 'approved']);

        $this->grantPermission();
    }

    /**
     * Create `approve expense` and hand it to the roles named above.
     *
     * Written against the tables rather than Spatie's models so it cannot fail on
     * a server whose permission cache is stale — the cache is flushed at the end
     * either way, because Spatie reads roles from it and would otherwise refuse
     * the new permission until the next cache expiry.
     */
    private function grantPermission(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $guard = DB::table('permissions')->value('guard_name') ?: 'web';

        $permissionId = DB::table('permissions')
            ->where('name', 'approve expense')->where('guard_name', $guard)
            ->value('id');

        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name'       => 'approve expense',
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')->whereIn('name', self::GRANT_TO)->pluck('id');

        foreach ($roleIds as $roleId) {
            $already = DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)->where('role_id', $roleId)->exists();

            if (!$already) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id'       => $roleId,
                ]);
            }
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_company_approval_index');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'approved_at']);
        });

        $permissionId = DB::table('permissions')->where('name', 'approve expense')->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app()['cache']->forget('spatie.permission.cache');
    }
};
