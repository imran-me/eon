<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeeResignation;
use App\Models\EmployeeResignationAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;


class ResignationController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view resignation', only: ['index', 'printList']),
            new Middleware('permission:create resignation', only: ['create', 'store']),
            new Middleware('permission:edit resignation', only: ['edit', 'update']),
            new Middleware('permission:delete resignation', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $role = null)
    {
        try {
            $query = $this->resignationQuery($request);

            $resignations = (clone $query)->paginate(15);
            $employees = $this->employeeQuery()->with('profile.designation')->orderBy('name')->get();
            $pendingCount = (clone $query)->where('status', 'pending')->count();
            $approvedCount = (clone $query)->where('status', 'approved')->count();
            $rejectedCount = (clone $query)->where('status', 'rejected')->count();

            return view('panel.resignation.index', compact(
                'resignations',
                'employees',
                'pendingCount',
                'approvedCount',
                'rejectedCount'
            ));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the resignations page.');
        }
    }

    /**
     * Print all matching resignation records.
     */
    public function printList(Request $request, $role = null)
    {
        try {
            $resignations = $this->resignationQuery($request)->get();
            $filterLabels = [
                'employee' => request('employee_id')
                    ? User::find(request('employee_id'))?->name
                    : null,
            ];
            $company = Company::orderBy('id')->first();

            return view('panel.resignation.print', compact('resignations', 'filterLabels', 'company'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the resignation print page.');
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $employees = $this->employeeQuery()->where('status', 'active')->get();
            return view('panel.resignation.create', compact('employees'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the resignation creation page.');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:users,id',
                'resign_date' => 'required|date',
                'notice_period_days' => 'nullable|integer|min:0',
                'reason' => 'nullable|string',
                'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            ]);
            if (!$this->employeeQuery()->where('id', $request->employee_id)->exists()) {
                abort(403, 'You are not allowed to file a resignation for this employee.');
            }
            if ($request->status == 'approved') {
                $request->merge([
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            }
            $resignation = EmployeeResignation::create([
                'employee_id' => $request->employee_id,
                'resign_date' => $request->resign_date,
                'last_working_day' => $request->last_working_day,
                'resign_type' => $request->resign_type,
                'notice_period_days' => $request->notice_period_days,
                'status' => $request->status ?? 'pending',
                'reason' => $request->reason,
                'exit_note' => $request->exit_note,
                'approved_by' => $request->status === 'approved' ? auth()->id() : null,
                'approved_at' => $request->status === 'approved' ? now() : null,
            ]);
            $this->storeAttachments($request, $resignation);
            //user status update can be handled via observer or event listener
            if ($request->status === 'approved') {
                User::findOrFail($request->employee_id)
                    ->update(['status' => 'resigned']);
            }
            $roleSlug = Str::slug(Auth::user()->getRoleNames()->first());
            return redirect()->route('role.resignations.index', ['role' => $roleSlug])->with('success', 'Resignation updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while submitting the resignation.')->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($role, string $id)
    {
        try {
            $resignation = EmployeeResignation::with([
                'user.profile.designation',
                'user.profile.department',
                'user.company',
                'approvedBy',
                'attachments',
            ])->findOrFail($id);
            $this->assertResignationAccess($resignation);
            return view('panel.resignation.show', compact('resignation'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Resignation record not found.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($role, string $id)
    {
        try {
            $resignation = EmployeeResignation::with('attachments')->findOrFail($id);
            $this->assertResignationAccess($resignation);
            $employees = $this->employeeQuery()->where('status', 'resigned')->get();
            return view('panel.resignation.edit', compact('resignation', 'employees'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the resignation edit page.');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, string $id)
    {
        try {
            $resignation = EmployeeResignation::findOrFail($id);
            $this->assertResignationAccess($resignation);
            $request->validate([
                'employee_id' => 'required|exists:users,id',
                'resign_date' => 'required|date',
                'notice_period_days' => 'nullable|integer|min:0',
                'reason' => 'nullable|string',
                'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            ]);
            if (!$this->employeeQuery()->where('id', $request->employee_id)->exists()) {
                abort(403, 'You are not allowed to file a resignation for this employee.');
            }
            if ($request->status == 'approved' && $resignation->status != 'approved') {
                $request->merge([
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            }
            $resignation->update([
                'employee_id' => $request->employee_id,
                'resign_date' => $request->resign_date,
                'last_working_day' => $request->last_working_day,
                'resign_type' => $request->resign_type,
                'notice_period_days' => $request->notice_period_days,
                'status' => $request->status,
                'reason' => $request->reason,
                'exit_note' => $request->exit_note,
                'approved_by' => $request->status === 'approved' ? auth()->id() : null,
                'approved_at' => $request->status === 'approved' ? now() : null,
            ]);
            $this->storeAttachments($request, $resignation);
            //user status update can be handled via observer or event listener
            if ($request->status === 'approved') {
                User::findOrFail($request->employee_id)
                    ->update(['status' => 'resigned']);
            }
            $roleSlug = Str::slug(Auth::user()->getRoleNames()->first());
            return redirect()->route('role.resignations.index', ['role' => $roleSlug])->with('success', 'Resignation updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the resignation.')->withInput();
        }
    }

    public function approve($role, string $id)
    {
        try {
            $resignation = EmployeeResignation::findOrFail($id);
            $this->assertResignationAccess($resignation);
            $resignation->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            User::findOrFail($resignation->employee_id)->update(['status' => 'resigned']);
            $roleSlug = Str::slug(Auth::user()->getRoleNames()->first());
            return redirect()->route('role.resignations.index', ['role' => $roleSlug])->with('success', 'Resignation approved successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while approving the resignation.');
        }
    }

    public function reject($role, string $id)
    {
        try {
            $resignation = EmployeeResignation::findOrFail($id);
            $this->assertResignationAccess($resignation);
            $resignation->update(['status' => 'rejected']);
            $roleSlug = Str::slug(Auth::user()->getRoleNames()->first());
            return redirect()->route('role.resignations.index', ['role' => $roleSlug])->with('success', 'Resignation rejected.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while rejecting the resignation.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Delete a single resignation attachment.
     */
    public function deleteAttachment(Request $request, $role, $resignationId, $attachmentId)
    {
        $attachment = EmployeeResignationAttachment::where('id', $attachmentId)
            ->where('employee_resignation_id', $resignationId)
            ->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found.',
            ]);
        }

        if (file_exists(public_path($attachment->file_path))) {
            unlink(public_path($attachment->file_path));
        }

        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully.',
        ]);
    }

    /**
     * Store uploaded files against a resignation record.
     */
    private function storeAttachments(Request $request, EmployeeResignation $resignation): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $attachment) {
            if ($attachment->isValid()) {
                $path = $this->storePublicFile(
                    $attachment,
                    'uploads/resignation-attachments/' . $resignation->id
                );

                EmployeeResignationAttachment::create([
                    'employee_resignation_id' => $resignation->id,
                    'file_path' => $path,
                    'file_name' => $attachment->getClientOriginalName(),
                ]);
            }
        }
    }

    private function storePublicFile(UploadedFile $file, string $directory): string
    {
        $fileName = uniqid() . '_' . time() . '.' . strtolower($file->getClientOriginalExtension());

        if (! file_exists(public_path($directory))) {
            mkdir(public_path($directory), 0777, true);
        }

        $file->move(public_path($directory), $fileName);

        return trim($directory, '/') . '/' . $fileName;
    }

    private function resignationQuery(Request $request)
    {
        return EmployeeResignation::with(['user', 'approvedBy'])
            ->when(!Auth::user()->hasPermissionTo('view all resignation'), function ($query) {
                $query->where('employee_id', Auth::id());
            })
            ->when($request->filled('employee_id'), function ($query) use ($request) {
                $query->where('employee_id', $request->employee_id);
            })
            ->when($request->filled('resign_type'), function ($query) use ($request) {
                $query->where('resign_type', $request->resign_type);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('resign_date'), function ($query) use ($request) {
                $query->whereDate('resign_date', $request->resign_date);
            })
            ->orderBy('created_at', 'desc');
    }

    /**
     * Employees selectable by the current user: everyone for holders of
     * "view all resignation", otherwise only themselves.
     */
    private function employeeQuery()
    {
        if (Auth::user()->hasPermissionTo('view all resignation')) {
            return User::role('employee');
        }

        return User::where('id', Auth::id());
    }

    /**
     * Block direct-URL access to another employee's resignation record.
     */
    private function assertResignationAccess(EmployeeResignation $resignation): void
    {
        if (Auth::user()->hasPermissionTo('view all resignation')) {
            return;
        }

        if ((int) $resignation->employee_id !== (int) Auth::id()) {
            abort(403, 'You are not allowed to access this resignation record.');
        }
    }
}
