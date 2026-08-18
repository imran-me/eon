<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class TaskProjectController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view task project|view all task project', only: ['index']),
            new Middleware('permission:create task project', only: ['store']),
            new Middleware('permission:edit task project', only: ['update']),
            new Middleware('permission:delete task project', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $userId = auth()->id();
        $query = Project::with('projectCategory', 'customer')
            ->where('type', 'task_project')
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => function ($q) {
                    $q->whereNotNull('completed_at');
                }
            ]);

        if ($request->filled('project_name')) {
            $query->where('project_name', 'like', '%' . $request->project_name . '%');
        }
        if ($request->filled('project_category_id')) {
            $query->where('project_category_id', $request->project_category_id);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ('employee' == Str::slug(Auth::user()->getRoleNames()->first()) && !Auth::user()->hasPermissionTo('view all task project')) {
            $query->whereJsonContains('team_members', (string) $userId);
        }

        $datas = $query->orderBy('created_at', 'desc')->paginate(30);

        $projectCategories = ProjectCategory::orderBy('name', 'asc')->get();
        $customers = Customer::orderBy('name', 'asc')->get();
        $companies = Company::orderBy('name', 'asc')->get();
        $departments = Department::orderBy('name', 'asc')->get();
        $employees = User::role('employee')->orderBy('name', 'asc')->where('status', 'active')->get();

        return view('projects.index', compact(
            'datas',
            'projectCategories',
            'departments',
            'customers',
            'employees',
            'companies'
        ));
    }
}
