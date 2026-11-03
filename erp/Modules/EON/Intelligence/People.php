<?php

namespace Modules\EON\Intelligence;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Who EON evaluates.
 *
 * This is a deliberate TWIN of EmployeeTaskOversightController::boardablePeople().
 * It could have called that method instead — but that method is private, and
 * making it public would mean editing a controller that is already live and
 * working. EON's whole licence is "a pure addition, nothing existing changes",
 * and a duplicated forty-line query is a cheaper price than a shared file EON
 * now has an opinion about.
 *
 * The consequence, stated plainly for whoever reads this next: THE TWO MUST
 * AGREE. If the oversight picker's rule for "who can have a board" changes, the
 * rule here changes with it, or the two screens start listing different people
 * and nobody will know which is right.
 *
 * The rule itself (unchanged from the original): the install carries 120 user
 * accounts, most of them travel agents, vendors and customers who cannot have a
 * task board at all. Written as a POSITIVE test so it self-corrects — hold any
 * role that is not agent/vendor/customer, OR be on a task, OR have created one,
 * OR sit on a project team.
 */
final class People
{
    private const EXTERNAL_ROLES = ['agent', 'vendor', 'customer'];

    /**
     * Everybody EON can evaluate, with the fields the pages display.
     *
     * @param  string|null $q  free-text filter over name, employee ID, email,
     *                         phone, company, department, designation and role
     */
    public static function evaluable(?string $q = null): Collection
    {
        try {
            return User::query()
                ->with([
                    'company:id,name,short_name',
                    'profile:id,user_id,department_id,designation_id',
                    'profile.department:id,name',
                    'profile.designation:id,name',
                    'roles:id,name',
                ])
                ->where(function ($w) {
                    $w->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', self::EXTERNAL_ROLES))
                        ->orWhereHas('roles', fn ($r) => $r->whereNotIn('name', self::EXTERNAL_ROLES))
                        ->orWhereHas('tasks')
                        ->orWhereExists(fn ($sq) => $sq->selectRaw('1')->from('tasks')
                            ->whereColumn('tasks.created_by', 'users.id')
                            ->whereNull('tasks.deleted_at'))
                        ->orWhereIn('users.id', self::projectTeamIds() ?: [0]);
                })
                ->when($q !== null && $q !== '', function ($query) use ($q) {
                    $query->where(function ($w) use ($q) {
                        $w->where('users.name', 'like', "%{$q}%")
                            ->orWhere('users.email', 'like', "%{$q}%")
                            ->orWhere('users.employee_id_no', 'like', "%{$q}%")
                            ->orWhere('users.phone', 'like', "%{$q}%")
                            ->orWhereHas('company', fn ($c) => $c
                                ->where('name', 'like', "%{$q}%")
                                ->orWhere('short_name', 'like', "%{$q}%"))
                            ->orWhereHas('profile.department', fn ($d) => $d->where('name', 'like', "%{$q}%"))
                            ->orWhereHas('profile.designation', fn ($d) => $d->where('name', 'like', "%{$q}%"))
                            ->orWhereHas('roles', fn ($r) => $r->where('name', 'like', "%{$q}%"));
                    });
                })
                ->orderBy('users.name')
                ->get();
        } catch (Throwable $e) {
            return collect();
        }
    }

    /** team_members is a JSON array of stringified ids on projects. */
    private static function projectTeamIds(): array
    {
        try {
            return Project::pluck('team_members')
                ->flatMap(fn ($j) => is_array($j) ? $j : (json_decode((string) $j, true) ?: []))
                ->map(fn ($v) => (int) $v)
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Two letters for the avatar when there is no photo. */
    public static function initials(?string $name): string
    {
        return collect(explode(' ', trim((string) $name)))
            ->filter()
            ->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('') ?: '?';
    }
}
