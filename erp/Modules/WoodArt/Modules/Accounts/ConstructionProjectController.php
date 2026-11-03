<?php

namespace Modules\WoodArt\Modules\Accounts;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;

class ConstructionProjectController extends Controller
{
    public function create(): View
    {
        ProjectContext::assertCanView();
        abort_unless(auth()->user()?->can('create expense'), 403);

        return view('woodart::construction.project-create', $this->pageData());
    }

    public function store(Request $request): RedirectResponse
    {
        ProjectContext::assertCanView();
        abort_unless(auth()->user()?->can('create expense'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('expense_departments', 'name')->where('company_id', ProjectContext::id())->whereNull('deleted_at')],
            'project_code' => ['required', 'string', 'max:40', Rule::unique('project_terms', 'project_code')],
            'client_party_id' => 'required|integer|exists:users,id', 'manager_user_id' => 'required|integer|exists:users,id',
            'project_type' => 'required|string|max:80', 'construction_status' => 'required|in:planning,in_progress,on_hold,completed',
            'location' => 'required|string|max:255', 'contract_type' => 'required|string|max:40',
            'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date', 'priority' => 'required|in:low,medium,high,urgent',
            'contract_value' => 'required|numeric|min:0|max:999999999999.99', 'estimated_budget' => 'nullable|numeric|min:0|max:999999999999.99',
            'currency' => 'required|in:BDT', 'billing_type' => 'required|string|max:40', 'physical_progress_target' => 'required|integer|min:0|max:100',
            'project_description' => 'required|string|max:2000', 'tags' => 'nullable|string|max:500', 'internal_notes' => 'nullable|string|max:2000',
            'team_member_ids' => 'nullable|array|max:20', 'team_member_ids.*' => 'integer|exists:users,id',
            'documents' => 'nullable|array|max:10', 'documents.*' => 'file|max:10240|mimes:pdf,png,jpg,jpeg,doc,docx,xls,xlsx',
            'enable_budget_tracking' => 'nullable|boolean', 'track_material_costs' => 'nullable|boolean', 'require_approval_workflow' => 'nullable|boolean', 'allow_client_collections' => 'nullable|boolean',
        ]);

        $documentPaths = [];
        foreach ($request->file('documents', []) as $document) {
            $documentPaths[] = ['name' => $document->getClientOriginalName(), 'path' => $document->store('construction/projects', 'public')];
        }

        $departmentId = DB::transaction(function () use ($data, $documentPaths) {
            $departmentId = DB::table('expense_departments')->insertGetId(['company_id' => ProjectContext::id(), 'user_id' => auth()->id(), 'name' => $data['name'], 'description' => $data['project_type'], 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('project_terms')->insert([
                'expense_department_id' => $departmentId, 'project_code' => $data['project_code'], 'client_party_id' => $data['client_party_id'], 'manager_user_id' => $data['manager_user_id'],
                'project_type' => $data['project_type'], 'construction_status' => $data['construction_status'], 'location' => $data['location'], 'contract_type' => $data['contract_type'],
                'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'priority' => $data['priority'], 'contract_value' => $data['contract_value'], 'estimated_budget' => $data['estimated_budget'] ?? null,
                'currency' => $data['currency'], 'billing_type' => $data['billing_type'], 'physical_progress_target' => $data['physical_progress_target'], 'progress_percent' => 0,
                'project_description' => $data['project_description'], 'tags' => json_encode(array_values(array_filter(array_map('trim', explode(',', $data['tags'] ?? ''))))), 'internal_notes' => $data['internal_notes'] ?? null,
                'settings' => json_encode(['enable_budget_tracking' => (bool) ($data['enable_budget_tracking'] ?? false), 'track_material_costs' => (bool) ($data['track_material_costs'] ?? false), 'require_approval_workflow' => (bool) ($data['require_approval_workflow'] ?? false), 'allow_client_collections' => (bool) ($data['allow_client_collections'] ?? false)]),
                'team_member_ids' => json_encode(array_values($data['team_member_ids'] ?? [])), 'document_paths' => json_encode($documentPaths), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $departmentId;
        });

        if ($request->input('after') === 'new') {
            return redirect()->route(ProjectContext::routeName('projects.create'), ['role' => $request->route('role')])->with('success', 'Project created successfully. Add another project.');
        }

        return redirect()->route(ProjectContext::routeName('project.summary'), ['role' => $request->route('role'), 'department' => $departmentId])->with('success', 'Project created successfully.');
    }

    private function pageData(): array
    {
        $users = DB::table('users')->whereNull('deleted_at')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'image', 'company_id']);

        return ['waModule' => 'accounts', 'waRoute' => ProjectContext::routeName(), 'waCompanyName' => ProjectContext::name(), 'waIcon' => 'buildings', 'section' => 'projects', 'sections' => [], 'title' => 'Create New Project', 'subtitle' => 'Set up a new construction project with all relevant details, financials and team members.', 'clients' => $users, 'managers' => $users->where('company_id', ProjectContext::id())->values(), 'teamMembers' => $users->where('company_id', ProjectContext::id())->values()];
    }
}
