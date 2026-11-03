<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyType extends Model
{
    /**
     * `model_class` says where this type's parties come from. Two shapes:
     *
     *   'App\Models\Supplier'  — an Eloquent model; one row per party.
     *   'role:vendor'          — the users holding that Spatie role, i.e.
     *                            exactly the roster Party Management →
     *                            Manage Vendor lists.
     *
     * The second shape exists because Travels-style companies keep their
     * vendors/agents/customers as `users` with a role, not as rows in
     * `suppliers`/`customers` — so pointing their party types at Supplier
     * offered the wrong people entirely. Empty still means free text.
     */
    public const ROLE_PREFIX = 'role:';

    protected $fillable = ['company_id', 'name', 'slug', 'model_class'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** True when parties are picked from a list rather than typed in. */
    public function hasModel(): bool
    {
        return !empty($this->model_class);
    }

    /** The Spatie role this type draws its parties from, or null. */
    public function partyRole(): ?string
    {
        $value = (string) $this->model_class;

        return str_starts_with($value, self::ROLE_PREFIX)
            ? substr($value, strlen(self::ROLE_PREFIX)) ?: null
            : null;
    }

    public function getParties(?int $companyId = null): \Illuminate\Support\Collection
    {
        if ($role = $this->partyRole()) {
            return $this->partiesByRole($role);
        }

        if (!$this->model_class || !class_exists($this->model_class)) {
            return collect();
        }

        $query = ($this->model_class)::select('id', 'name')->orderBy('name');

        if ($companyId) {
            $instance = new ($this->model_class)();
            if (in_array('company_id', $instance->getFillable())) {
                $query->where('company_id', $companyId);
            }
        }

        return $query->get();
    }

    /**
     * Resolve one picked party back to its record, whichever source it came
     * from. Callers must go through this instead of `model_class::find()` —
     * a role-backed type has no class to call statically.
     */
    public function findParty($id): ?Model
    {
        if (!$id) {
            return null;
        }

        if ($role = $this->partyRole()) {
            return $this->roleQuery($role)->find($id);
        }

        if (!$this->model_class || !class_exists($this->model_class)) {
            return null;
        }

        return ($this->model_class)::find($id);
    }

    /** Label for the "Maps To" badge on the Party Types screen. */
    public function sourceLabel(): string
    {
        if ($role = $this->partyRole()) {
            return ucfirst($role) . ' users';
        }

        return $this->model_class ? class_basename($this->model_class) : 'Free text';
    }

    /**
     * Active parties only — `status === 'active'` is the same test Party
     * Management uses to paint its Active / Inactive badge, so the picker
     * offers exactly the people that list shows as active. A null status
     * counts as inactive there, and counts as inactive here too.
     *
     * Deliberately NOT company-scoped: Party Management lists every user
     * holding the role regardless of `company_id` (a good number are null),
     * and the picker has to offer the same people or a schedule cannot be
     * raised against them.
     */
    protected function partiesByRole(string $role): \Illuminate\Support\Collection
    {
        return $this->roleQuery($role)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * No status filter here on purpose. This resolves a party that was
     * already picked; if they were deactivated between opening the form and
     * submitting it, naming them correctly beats failing with "party name is
     * required". Filtering is the picker's job, not the lookup's.
     */
    protected function roleQuery(string $role): \Illuminate\Database\Eloquent\Builder
    {
        return User::whereHas('roles', fn($q) => $q->where('name', $role));
    }
}
