<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ExpenseDepartment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PartyAssignmentService
{
    public static function rules(): array
    {
        return [
            'company_ids' => ['required', 'array', 'min:1'],
            'company_ids.*' => ['integer', 'distinct', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'distinct', Rule::exists('expense_departments', 'id')->whereNull('deleted_at')],
        ];
    }

    public static function formData(?User $party, string $type): array
    {
        $companies = self::allowedCompanies();
        $projects = ExpenseDepartment::query()->active()->whereIn('company_id', $companies->pluck('id')->intersect([3, 6]))
            ->orderBy('company_id')->orderBy('name')->get(['id', 'company_id', 'name']);

        $selectedCompanies = $party
            ? DB::table('company_party')->where('user_id', $party->id)->where('party_type', $type)->pluck('company_id')->map(fn ($id) => (int) $id)->all()
            : [];
        $selectedProjects = $party && DB::getSchemaBuilder()->hasTable('project_party')
            ? DB::table('project_party')->where('user_id', $party->id)->where('party_type', $type)->pluck('expense_department_id')->map(fn ($id) => (int) $id)->all()
            : [];

        // Create forms opened from a company dashboard or a project page should
        // already know their context. Only accept ids the current user may use;
        // a hand-edited query string must never expand their company access.
        if (! $party) {
            $contextCompany = request()->integer('company');
            $contextProject = request()->integer('project');
            $allowedCompanyIds = $companies->pluck('id')->map(fn ($id) => (int) $id);

            $project = $contextProject ? $projects->firstWhere('id', $contextProject) : null;
            if ($project) {
                $contextCompany = (int) $project->company_id;
                $selectedProjects = [(int) $project->id];
            }
            if ($contextCompany && $allowedCompanyIds->contains((int) $contextCompany)) {
                $selectedCompanies = [(int) $contextCompany];
            }
        }

        return compact('companies', 'projects', 'selectedCompanies', 'selectedProjects');
    }

    public static function sync(User $party, string $type, array $companyIds, array $projectIds = []): void
    {
        $companyIds = collect($companyIds)->map(fn ($id) => (int) $id)->unique()->values();
        $projectIds = collect($projectIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($companyIds->diff(self::allowedCompanies()->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['company_ids' => 'You cannot assign a party to that company.']);
        }

        $invalidProjects = ExpenseDepartment::query()->whereIn('id', $projectIds)
            ->whereNotIn('company_id', $companyIds)->pluck('name');
        if ($invalidProjects->isNotEmpty()) {
            throw ValidationException::withMessages([
                'project_ids' => 'Every selected project must belong to one of the selected companies.',
            ]);
        }

        DB::table('company_party')->where('user_id', $party->id)->where('party_type', $type)
            ->whereNotIn('company_id', $companyIds)->delete();
        foreach ($companyIds as $companyId) {
            DB::table('company_party')->updateOrInsert(
                ['company_id' => $companyId, 'user_id' => $party->id, 'party_type' => $type],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        DB::table('project_party')->where('user_id', $party->id)->where('party_type', $type)
            ->whereNotIn('expense_department_id', $projectIds)->delete();
        foreach ($projectIds as $projectId) {
            DB::table('project_party')->updateOrInsert(
                ['expense_department_id' => $projectId, 'user_id' => $party->id, 'party_type' => $type],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private static function allowedCompanies()
    {
        $user = auth()->user();
        return Company::query()->where('status', 1)
            ->when(! $user?->is_super_admin, fn ($query) => $query->whereKey($user?->company_id))
            ->orderBy('order')->orderBy('name')->get(['id', 'name']);
    }
}
