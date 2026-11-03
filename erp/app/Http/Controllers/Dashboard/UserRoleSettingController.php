<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserRoleSetting\Access;
use App\Support\UserRoleSetting\ModuleMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * User Role Setting (owner, 2026-11-01): a NEW role-permission system, separate from the existing
 * Role Permission screen and Spatie roles, tried on one employee first. Super admin only.
 * Step 1: the access map. Step 2: pick a person, save their ticks to `user_role_settings`.
 * Nothing reads that table to grant or refuse access yet.
 */
class UserRoleSettingController extends Controller
{
    private const TABLE = 'user_role_settings';

    private function guard(): void
    {
        abort_unless(auth()->user()?->is_super_admin, 403, 'Only a super admin can open User Role Setting.');
    }

    private static function ready(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function index(string $role)
    {
        $this->guard();

        $companies = [];
        try {
            foreach (DB::table('companies')->whereNull('deleted_at')->get(['id', 'name', 'short_name', 'icon']) as $c) {
                $icon = (string) $c->icon;
                $companies[$c->id] = [
                    'name' => $c->name,
                    'short' => $c->short_name ?: $c->name,
                    'logo' => $icon === '' ? null : (Str::startsWith($icon, ['http://', 'https://']) ? $icon : asset($icon)),
                ];
            }
        } catch (\Throwable $e) {
            $companies = [];   // the map still renders, with the labels it carries
        }

        return view('user-role-settings.index', [
            'role' => $role,
            'tree' => ModuleMap::tree(),
            'basic' => ModuleMap::basic(),
            'companies' => $companies,
            'ready' => self::ready(),
        ]);
    }

    /** Employees with roles: everyone who has a saved setting, module-wise. */
    public function employees(string $role)
    {
        $this->guard();
        $rows = collect();
        if (self::ready()) {
            $saved = DB::table(self::TABLE)->orderByDesc('updated_at')->get();
            $users = User::withTrashed()->whereIn('id', $saved->pluck('user_id')->merge($saved->pluck('updated_by'))->filter())->get()->keyBy('id');
            $leaves = ModuleMap::leaves();
            $modules = ModuleMap::modules();
            $companies = [];
            try {
                $companies = DB::table('companies')->pluck('short_name', 'id')->all();
            } catch (\Throwable $e) {
            }
            foreach ($saved as $r) {
                $u = $users[$r->user_id] ?? null;
                if (! $u) {
                    continue;
                }
                // per module: its pages given, and the actions given anywhere in it
                $mods = [];
                foreach ((array) json_decode($r->access, true) as $key => $letters) {
                    $mk = $leaves[$key][1] ?? null;
                    if (! $mk || ! isset($modules[$mk])) {
                        continue;
                    }
                    $mods[$mk] ??= ['label' => $modules[$mk][0], 'company' => $modules[$mk][1] === 4 ? 'Group-wide' : ($companies[$modules[$mk][1]] ?? 'Company #'.$modules[$mk][1]),
                        'pages' => 0, 'total' => count(array_filter($leaves, fn ($l) => $l[1] === $mk)), 'acts' => ''];
                    $mods[$mk]['pages']++;
                    $mods[$mk]['acts'] = implode('', array_filter(str_split('vced'), fn ($a) => str_contains($mods[$mk]['acts'].$letters, $a)));
                }
                $rows->push(['person' => $this->person($u), 'trashed' => $u->trashed(), 'modules' => array_values($mods),
                    'applied' => (bool) ($r->applied ?? false), 'applied_at' => $r->applied_at ?? null,
                    'pages' => (int) $r->pages, 'updated_at' => $r->updated_at, 'by' => ($users[$r->updated_by] ?? null)?->name]);
            }
        }

        return view('user-role-settings.employees', ['role' => $role, 'rows' => $rows, 'ready' => self::ready(),
            'applyReady' => self::applyReady(), 'master' => Access::masterOn(),
            'basicPages' => collect(ModuleMap::basic())->sum(fn ($g) => count($g['items']))]);
    }

    /** People to choose from: anyone not a super admin, by name / email / phone / ID no. */
    public function people(Request $request, string $role): JsonResponse
    {
        $this->guard();
        $q = trim((string) $request->query('q', ''));
        $users = User::query()->where(fn ($w) => $w->where('is_super_admin', false)->orWhereNull('is_super_admin'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")->orWhere('employee_id_no', 'like', "%{$q}%")))
            ->orderBy('name')->limit(20)->get();

        return response()->json(['people' => $users->map(fn (User $u) => $this->person($u))->values()]);
    }

    /** Everyone who has a saved setting. */
    public function saved(string $role): JsonResponse
    {
        $this->guard();
        if (! self::ready()) {
            return response()->json(['ready' => false, 'saved' => []]);
        }
        $rows = DB::table(self::TABLE)->orderByDesc('updated_at')->get();
        $users = User::whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return response()->json(['ready' => true, 'saved' => $rows->filter(fn ($r) => $users->has($r->user_id))->map(fn ($r) => $this->person($users[$r->user_id]) + [
            'pages' => (int) $r->pages, 'modules' => (int) $r->modules, 'updated_at' => $r->updated_at,
        ])->values()]);
    }

    public function show(string $role, int $user): JsonResponse
    {
        $this->guard();
        $u = User::findOrFail($user);
        $row = self::ready() ? DB::table(self::TABLE)->where('user_id', $u->id)->first() : null;

        return response()->json(['person' => $this->person($u), 'access' => $row ? (json_decode($row->access, true) ?: []) : (object) [],
            'saved_at' => $row?->updated_at]);
    }

    public function save(Request $request, string $role, int $user): JsonResponse
    {
        $this->guard();
        if (! self::ready()) {
            return response()->json(['success' => false, 'message' => 'User Role Setting is not installed on this server yet (its migration has not run).'], 422);
        }
        $u = User::findOrFail($user);
        if ($u->is_super_admin) {
            return response()->json(['success' => false, 'message' => 'A super admin already has everything.'], 422);
        }
        $request->validate(['access' => ['nullable', 'array']]);

        // only pages on the map, only the actions each page has; Create / Edit / Delete carry View
        $leaves = ModuleMap::leaves();
        $access = [];
        foreach ((array) $request->input('access', []) as $key => $letters) {
            if (! is_string($key) || ! isset($leaves[$key]) || ! is_string($letters)) {
                continue;
            }
            $allowed = $leaves[$key][0];
            $got = implode('', array_filter(str_split('vced'), fn ($a) => str_contains($letters, $a) && str_contains($allowed, $a)));
            if ($got !== '' && ! str_contains($got, 'v') && str_contains($allowed, 'v')) {
                $got = 'v'.$got;
            }
            if ($got !== '') {
                $access[$key] = $got;
            }
        }
        $modules = count(array_unique(array_map(fn ($k) => $leaves[$k][1], array_keys($access))));

        if (! $access) {
            DB::table(self::TABLE)->where('user_id', $u->id)->delete();

            return response()->json(['success' => true, 'message' => 'Nothing ticked — '.$u->name.'\'s setting was removed.', 'removed' => true]);
        }
        DB::table(self::TABLE)->updateOrInsert(['user_id' => $u->id], [
            'access' => json_encode($access), 'pages' => count($access), 'modules' => $modules,
            'updated_by' => auth()->id(), 'updated_at' => now(),
            'created_at' => DB::table(self::TABLE)->where('user_id', $u->id)->value('created_at') ?? now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Saved for '.$u->name.': '.$modules.' module'.($modules === 1 ? '' : 's').', '.count($access).' page'.(count($access) === 1 ? '' : 's').'.',
            'access' => $access]);
    }

    /** Switch ONE person onto the new system (his old role stops counting) or back to his old role. */
    public function apply(Request $request, string $role, int $user): JsonResponse
    {
        $this->guard();
        if (! self::applyReady()) {
            return response()->json(['success' => false, 'message' => 'Applying is not installed on this server yet (its migration has not run).'], 422);
        }
        $request->validate(['on' => ['required', 'boolean']]);
        $u = User::findOrFail($user);
        if ($u->is_super_admin) {
            return response()->json(['success' => false, 'message' => 'A super admin cannot be switched to the new system.'], 422);
        }
        $row = DB::table(self::TABLE)->where('user_id', $u->id)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Save a setting for '.$u->name.' first.'], 422);
        }
        $on = $request->boolean('on');
        DB::table(self::TABLE)->where('user_id', $u->id)->update(['applied' => $on, 'applied_at' => $on ? now() : null, 'updated_by' => auth()->id(), 'updated_at' => now()]);
        Access::flush();

        return response()->json(['success' => true, 'applied' => $on, 'master' => Access::masterOn(),
            'message' => $on ? $u->name.' now uses the new system — his old role no longer counts.' : $u->name.' is back on his old role.']);
    }

    /** The master switch: off = nobody uses the new system, whatever is switched on. */
    public function master(Request $request, string $role): JsonResponse
    {
        $this->guard();
        if (! self::applyReady()) {
            return response()->json(['success' => false, 'message' => 'Applying is not installed on this server yet (its migration has not run).'], 422);
        }
        $request->validate(['on' => ['required', 'boolean']]);
        $on = $request->boolean('on');
        DB::table('user_role_setting_options')->updateOrInsert(['key' => 'enabled'],
            ['value' => $on ? '1' : '0', 'updated_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]);
        Access::flush();

        return response()->json(['success' => true, 'master' => $on,
            'message' => $on ? 'The new system is ON for everyone switched on.' : 'The new system is OFF — everybody is on their old role.']);
    }

    private static function applyReady(): bool
    {
        try {
            return self::ready() && Schema::hasColumn(self::TABLE, 'applied') && Schema::hasTable('user_role_setting_options');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function destroy(string $role, int $user): JsonResponse
    {
        $this->guard();
        if (self::ready()) {
            DB::table(self::TABLE)->where('user_id', $user)->delete();
        }

        return response()->json(['success' => true]);
    }

    /** @return array<string, mixed> */
    private function person(User $u): array
    {
        $roles = [];
        try {
            $roles = $u->getRoleNames()->values()->all();
        } catch (\Throwable $e) {
        }

        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'emp_no' => $u->employee_id_no,
            'image' => $u->image ? asset($u->image) : null, 'roles' => $roles];
    }
}
