<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrashController extends Controller
{
    /**
     * Supported trashable models with display config.
     * 'label'      — human-readable module name (shown in UI)
     * 'model'      — fully-qualified model class
     * 'titleField' — column used as the record title in the trash table
     * 'permission' — Spatie permission required to manage this module's trash
     */
    private function trashables(): array
    {
        return [
            'user' => [
                'label'      => 'Users',
                'icon'       => 'fa-users',
                'model'      => \App\Models\User::class,
                'titleField' => 'name',
                'permission' => 'delete user',
            ],
            'department' => [
                'label'      => 'Departments',
                'icon'       => 'fa-building',
                'model'      => \App\Models\Department::class,
                'titleField' => 'name',
                'permission' => 'delete department',
            ],
            'designation' => [
                'label'      => 'Designations',
                'icon'       => 'fa-id-badge',
                'model'      => \App\Models\Designation::class,
                'titleField' => 'name',
                'permission' => 'delete designation',
            ],
            'leave' => [
                'label'      => 'Leaves',
                'icon'       => 'fa-calendar-times',
                'model'      => \App\Models\Leave::class,
                'titleField' => 'reason',
                'permission' => 'delete leave',
            ],
            'loan' => [
                'label'      => 'Loans',
                'icon'       => 'fa-money-bill-wave',
                'model'      => \App\Models\Loan::class,
                'titleField' => 'amount',
                'permission' => 'delete loan',
            ],
            'expense' => [
                'label'      => 'Expenses',
                'icon'       => 'fa-receipt',
                'model'      => \App\Models\Expense::class,
                'titleField' => 'title',
                'permission' => 'delete expense',
            ],
            'employee_salary' => [
                'label'      => 'Employee Salaries',
                'icon'       => 'fa-wallet',
                'model'      => \App\Models\EmployeeSalary::class,
                'titleField' => 'basic_salary',
                'permission' => 'delete employee salary',
            ],
            'visa_process' => [
                'label'      => 'Visa Processes',
                'icon'       => 'fa-passport',
                'model'      => \App\Models\VisaProcess::class,
                'titleField' => 'applicant_name',
                'permission' => 'delete visa process',
            ],
            'visa_category' => [
                'label'      => 'Visa Categories',
                'icon'       => 'fa-tags',
                'model'      => \App\Models\VisaCategory::class,
                'titleField' => 'name',
                'permission' => 'delete visa category',
            ],
            'advance_salary' => [
                'label'      => 'Advance Salaries',
                'icon'       => 'fa-hand-holding-usd',
                'model'      => \App\Models\AdvanceSalary::class,
                'titleField' => 'amount',
                'permission' => 'delete advance salary',
            ],
            'bank' => [
                'label'      => 'Banks',
                'icon'       => 'fa-university',
                'model'      => \App\Models\Bank::class,
                'titleField' => 'name',
                'permission' => 'delete bank',
            ],
            'bank_transfer' => [
                'label'      => 'Bank Transfers',
                'icon'       => 'fa-exchange-alt',
                'model'      => \App\Models\BankTransfer::class,
                'titleField' => 'amount',
                'permission' => 'delete bank transfer',
            ],
            'account' => [
                'label'      => 'Accounts',
                'icon'       => 'fa-book',
                'model'      => \App\Models\Account::class,
                'titleField' => 'name',
                'permission' => 'delete account',
            ],
            'project' => [
                'label'      => 'Projects',
                'icon'       => 'fa-project-diagram',
                'model'      => \App\Models\Project::class,
                'titleField' => 'project_name',
                'permission' => 'delete project',
            ],
            'task' => [
                'label'      => 'Tasks',
                'icon'       => 'fa-tasks',
                'model'      => \App\Models\Task::class,
                'titleField' => 'name',
                'permission' => 'delete task',
            ],
             
        ];
    }

    /**
     * Show centralized trash page.
     */
    public function index(Request $request)
    {
        $filter   = $request->input('module', 'all');
        $trashables = $this->trashables();

        // Collect deleted records the current user has permission to see
        $groups = [];
        foreach ($trashables as $key => $config) {
            if (!Auth::user()->can($config['permission'])) {
                continue;
            }

            if ($filter !== 'all' && $filter !== $key) {
                continue;
            }

            $records = $config['model']::onlyTrashed()
                ->latest('deleted_at')
                ->get()
                ->map(function ($record) use ($key, $config) {
                    $title = $record->{$config['titleField']} ?? "ID #{$record->id}";
                    if ($key === 'account') {
                        $title = ($record->code ? $record->code . ' - ' : '') . $record->name;
                    }
                    return [
                        'id'         => $record->id,
                        'module'     => $key,
                        'label'      => $config['label'],
                        'icon'       => $config['icon'],
                        'title'      => $title,
                        'deleted_at' => $record->deleted_at,
                    ];
                });

            if ($records->isNotEmpty()) {
                $groups[$key] = [
                    'label'   => $config['label'],
                    'icon'    => $config['icon'],
                    'records' => $records,
                ];
            }
        }

        $totalCount = collect($groups)->sum(fn ($g) => count($g['records']));

        return view('trash.index', compact('groups', 'totalCount', 'filter', 'trashables'));
    }

    /**
     * Restore a soft-deleted record.
     */
    public function restore(Request $request,$role, string $module, string $id)
    {
        $config = $this->trashables()[$module] ?? null;
        if (!$config) {
            return back()->with('error', 'Invalid module.');
        }

        $this->authorize($config['permission']);

        $config['model']::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', "{$config['label']} record restored successfully.");
    }

    /**
     * Permanently delete a soft-deleted record.
     */
    public function forceDelete(Request $request,$role, string $module, string $id)
    {
        $config = $this->trashables()[$module] ?? null;
        if (!$config) {
            return back()->with('error', 'Invalid module.');
        }

        $this->authorize($config['permission']);

        $config['model']::onlyTrashed()->findOrFail($id)->forceDelete();

        return back()->with('success', "{$config['label']} record permanently deleted.");
    }

    /**
     * Restore all soft-deleted records for a module.
     */
    public function restoreAll(Request $request, string $module)
    {
        $config = $this->trashables()[$module] ?? null;
        if (!$config) {
            return back()->with('error', 'Invalid module.');
        }

        $this->authorize($config['permission']);

        $config['model']::onlyTrashed()->restore();

        return back()->with('success', "All {$config['label']} records restored.");
    }

    /**
     * Permanently delete all soft-deleted records for a module.
     */
    public function emptyModule(Request $request, string $module)
    {
        $config = $this->trashables()[$module] ?? null;
        if (!$config) {
            return back()->with('error', 'Invalid module.');
        }

        $this->authorize($config['permission']);

        $config['model']::onlyTrashed()->forceDelete();

        return back()->with('success', "All {$config['label']} records permanently deleted.");
    }
}
