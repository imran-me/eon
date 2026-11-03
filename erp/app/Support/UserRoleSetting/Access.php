<?php

namespace App\Support\UserRoleSetting;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The new system's answer to "may this person do X?" (owner, 2026-11-01).
 *
 * Guard rail: it answers ONLY for a person switched on (`user_role_settings.applied`) while the
 * master switch is on. For everyone else — and on ANY error — it returns null and the caller uses
 * the old Spatie check exactly as before. For a switched-on person his old role is not consulted:
 * Basic employee access + what is ticked on the map, nothing else.
 */
final class Access
{
    /** @var \WeakMap<User, array<string, true>|false>|null per user object = per request */
    private static ?\WeakMap $cache = null;

    /** Permission verb → map letter. */
    private const VERBS = [
        'view all' => 'v', 'view' => 'v', 'create' => 'c', 'edit' => 'e', 'update' => 'e', 'manage' => 'e',
        'approve' => 'e', 'reject' => 'e', 'fulfill' => 'e', 'disburse' => 'e', 'reimburse' => 'e', 'verify' => 'e',
        'escalate' => 'e', 'generate' => 'e', 'pay' => 'e', 'delete' => 'd',
    ];

    /** true / false when the new system decides, null = use the old system. */
    public static function decide(User $user, mixed $permission): ?bool
    {
        try {
            $name = is_string($permission) ? $permission : (is_object($permission) && isset($permission->name) ? (string) $permission->name : null);
            if ($name === null || $user->is_super_admin) {
                return null;
            }
            $allowed = self::allowedFor($user);
            if ($allowed === false) {
                return null;
            }

            return isset($allowed[strtolower(trim($name))]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Module-wide check for anything inside a module that has no permission of its own — the
     * super-admin-only tools (Trail, Correct, Undo, Delete wrong entry) and any button added later
     * (owner, 2026-11-01: "we are giving module wise, not every button"). Super admin: yes. A person
     * switched on: yes when he holds that action anywhere in the module. Everyone else: no — exactly
     * as before, when these were super admin only. New buttons call this; ModuleMap never changes.
     *
     * Letters: v view · c create · e edit · d delete.
     */
    public static function allows(string $module, string $letter = 'v', ?User $user = null): bool
    {
        try {
            $user ??= auth()->user();
            if (! $user instanceof User) {
                return false;
            }
            if ($user->is_super_admin) {
                return true;
            }
            $allowed = self::allowedFor($user);

            return $allowed !== false && isset($allowed['module:'.$module.':'.$letter]);
        } catch (\Throwable $e) {
            return (bool) ($user?->is_super_admin ?? false);
        }
    }

    /** Is the new system on, and is this person switched on? */
    public static function appliesTo(User $user): bool
    {
        try {
            return ! $user->is_super_admin && self::allowedFor($user) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function masterOn(): bool
    {
        try {
            return Schema::hasTable('user_role_setting_options')
                && DB::table('user_role_setting_options')->where('key', 'enabled')->value('value') === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static ?bool $systemOn = null;

    private static float $systemCheckedAt = 0;

    /**
     * Installed, master switch on, and at least one person switched on — remembered for 5 seconds,
     * so a check for everybody else costs nothing more while nobody is switched on.
     */
    private static function systemOn(): bool
    {
        if (self::$systemOn !== null && microtime(true) - self::$systemCheckedAt < 5) {
            return self::$systemOn;
        }
        $on = false;
        try {
            $on = Schema::hasTable('user_role_settings') && Schema::hasColumn('user_role_settings', 'applied') && self::masterOn()
                && DB::table('user_role_settings')->where('applied', true)->exists();
        } catch (\Throwable $e) {
            $on = false;
        }
        self::$systemCheckedAt = microtime(true);

        return self::$systemOn = $on;
    }

    /**
     * Every permission name this switched-on person holds under the new system, or false when the
     * new system does not apply to him.
     *
     * @return array<string, true>|false
     */
    private static function allowedFor(User $user): array|false
    {
        self::$cache ??= new \WeakMap;
        if (isset(self::$cache[$user])) {
            return self::$cache[$user];
        }
        $result = false;
        if (self::systemOn()) {
            $row = DB::table('user_role_settings')->where('user_id', $user->id)->where('applied', true)->first();
            if ($row) {
                $result = self::abilities((array) json_decode((string) $row->access, true));
            }
        }

        return self::$cache[$user] = $result;
    }

    /**
     * Ticked pages → permission names: each page's subjects × its letters ("v" = view + view all).
     *
     * @param  array<string, string>  $access
     * @return array<string, true>
     */
    public static function abilities(array $access): array
    {
        $out = [];
        foreach (ModuleMap::basicAbilities() as $a) {
            $out[$a] = true;
        }
        $leaves = ModuleMap::leaves();
        foreach ($access as $key => $letters) {
            if (! isset($leaves[$key]) || ! is_string($letters)) {
                continue;
            }
            // the whole module: any action given on any of its pages opens that action module-wide
            foreach (str_split($letters) as $letter) {
                if (str_contains($leaves[$key][0], $letter)) {
                    $out['module:'.$leaves[$key][1].':'.$letter] = true;
                }
            }
            foreach (ModuleMap::subjects($key) as $subject) {
                foreach (self::VERBS as $verb => $letter) {
                    if (str_contains($letters, $letter) && str_contains($leaves[$key][0], $letter)) {
                        $out[$verb.' '.$subject] = true;
                    }
                }
            }
        }

        return $out;
    }

    /** Forget what was worked out (after a save or a switch, for this request). */
    public static function flush(): void
    {
        self::$cache = null;
        self::$systemOn = null;
    }
}
