<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops a non-admin from browsing the ERP under an admin URL prefix.
 *
 * Every panel route sits under `/{role}/…` where the segment is constrained
 * only to "some role name that exists". Nothing tied it to the account making
 * the request, so an employee could load `/super-admin/dashboard` and every
 * link on that page would then be built with the same prefix.
 *
 * WHY THIS IS DELIBERATELY NARROW
 * ------------------------------
 * The obvious rule — "the segment must equal your own role" — is wrong for
 * this codebase, and would take the site down:
 *
 *   • The segment doubles as a FILTER in User Management. The sidebar renders
 *     one `/{roleSlug}/user` link per role, so HR legitimately opens
 *     `/employee/user` to list employees. A strict check 403s HR's own menu.
 *   • 946 of 1378 routes carry the segment and ~1000 view sites build URLs
 *     with it, several falling back to `?? 'admin'` for a user with no roles.
 *   • `$role` is never read for an authorization decision anywhere in the
 *     app — it only builds links — so binding it protects nothing that the
 *     per-route permissions and UserController's record-level guards do not
 *     already cover.
 *
 * So this blocks exactly the case that has real meaning: standing inside an
 * administrator's prefix without being one. Ordinary segments pass untouched,
 * which is what keeps the blast radius at "two slugs" rather than "946 routes".
 *
 * Anyone holding `view all users` is exempt: that is the ERP's existing marker
 * for cross-company administrative reach (super admin, admin), and they need
 * every segment for the User Management role tabs.
 */
class EnsurePanelRoleIsPermitted
{
    /**
     * Prefixes that assert administrative standing. Slug form, because the
     * route segment is always slugged ("super admin" → "super-admin").
     */
    private const PRIVILEGED_SLUGS = ['super-admin', 'superadmin', 'admin'];

    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->route('role');
        $user    = $request->user();

        // No segment, or not logged in yet — `auth` handles the second case.
        if (! is_string($segment) || ! $user) {
            return $next($request);
        }

        $slug = Str::slug($segment);

        // Ordinary prefix (employee, hr, crm, …) — unchanged behaviour.
        if (! in_array($slug, self::PRIVILEGED_SLUGS, true)) {
            return $next($request);
        }

        // Administrators: every segment stays open, including each other's.
        // Without this, a super admin clicking "Admin Users" in the sidebar —
        // which builds `/admin/user` — would be refused.
        if ($user->can('view all users')) {
            return $next($request);
        }

        // Holding the role itself is always enough.
        foreach ($user->getRoleNames() as $roleName) {
            if (Str::slug($roleName) === $slug) {
                return $next($request);
            }
        }

        abort(403, 'You are not permitted to browse under this panel.');
    }
}
