<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Notice;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class NoticeController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view users', only: ['index', 'show']),
            new Middleware('permission:create users', only: ['create', 'store']),
            new Middleware('permission:edit users', only: ['edit', 'update']),
            new Middleware('permission:delete users', only: ['destroy']),
        ];
    } 
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Notice::with('company','department');
        if ($request->has('title') && !empty($request->title)) {
            $query->where('title', 'like', '%'.$request->title.'%');
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('publish_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('publish_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->orderBy('created_at', 'desc')->paginate(30);
        
        $companies = Company::orderBy('name', 'asc')->get();
        $departments = Department::orderBy('name', 'asc')->get();

        return view('notices.index', compact(
            'datas',
            'companies',
            'departments',
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $existingNotice = Notice::where('title', $request->title)->first();

        $validator = Validator::make($request->all(), [
            'company_id'                => 'nullable|integer|exists:companies,id',
            'department_id'             => 'nullable|integer|exists:departments,id',
            'title'                     => 'required|string',
            'description'               => 'required|string',
            'status'                    => 'required|string|in:draft,published',
            'slide_image'               => 'nullable|image|max:2048',
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $slideImagePath = null;
        if ($request->hasFile('slide_image')) {
            $slideImagePath = $request->file('slide_image')->store('notices', 'public');
        }

        // Create
        $data = Notice::updateOrCreate([
            'title' => $request->title
        ],[
            'company_id' => $request->company_id,
            'department_id' => $request->department_id,
            'created_by' => Auth::id(),
            'description' => $request->description,
            'publish_date' => $request->publish_date,
            'expiry_date' => $request->expiry_date,
            'status' => $request->status,
            'icon' => $request->icon ?? 'fas fa-bullhorn',
            'card_color' => $request->card_color ?? '#f97316,#f59e0b',
            'text_color' => $request->text_color ?? '#ffffff',
            'badge_icon' => $request->badge_icon ?? 'fas fa-bell',
            'badge_label' => $request->badge_label ?? 'REMINDER',
            'slide_image' => $slideImagePath,
        ]);

        if ($data->status === 'published') {
            $action = !$existingNotice || $existingNotice->status !== 'published' ? 'published' : 'updated';
            NotificationService::notifyNoticePublished($data, $action);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Notices created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('dashboard.notices.index')->with('success', 'Data created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,$role, string $id)
    {
        $data = Notice::findOrFail($request->id);
        $oldStatus = $data->status;

        $validator = Validator::make($request->all(), [
            'company_id'                => 'nullable|integer|exists:companies,id',
            'department_id'             => 'nullable|integer|exists:departments,id',
            'title'                     => 'required|string',
            'description'               => 'required|string',
            'status'                    => 'required|string|in:draft,published',
            'slide_image'               => 'nullable|image|max:2048',
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            $slideImagePath = $data->slide_image;
            if ($request->hasFile('slide_image')) {
                if ($data->slide_image) {
                    Storage::disk('public')->delete($data->slide_image);
                }
                $slideImagePath = $request->file('slide_image')->store('notices', 'public');
            } elseif ($request->input('remove_slide_image')) {
                if ($data->slide_image) {
                    Storage::disk('public')->delete($data->slide_image);
                }
                $slideImagePath = null;
            }

            $data->update([
                'company_id' => $request->company_id,
                'department_id' => $request->department_id,
                'created_by' => Auth::id(),
                'title' => $request->title,
                'description' => $request->description,
                'publish_date' => $request->publish_date,
                'expiry_date' => $request->expiry_date,
                'status' => $request->status,
                'icon' => $request->icon ?? $data->icon ?? '📢',
                'card_color' => $request->card_color ?? $data->card_color ?? '#f97316,#f59e0b',
                'text_color' => $request->text_color ?? $data->text_color ?? '#ffffff',
                'badge_icon' => $request->badge_icon ?? $data->badge_icon ?? 'fas fa-bell',
                'badge_label' => $request->badge_label ?? $data->badge_label ?? 'REMINDER',
                'slide_image' => $slideImagePath,
            ]);

            if ($data->status === 'published') {
                $action = $oldStatus !== 'published' ? 'published' : 'updated';
                NotificationService::notifyNoticePublished($data, $action);
            }
            
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);    
        }

        return response()->json([
            'success' => true,
            'message' => 'Notices updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $data = Notice::find($request->item_id);
            if ($data) {
                if ($data->slide_image) {
                    Storage::disk('public')->delete($data->slide_image);
                }
                $data->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Notice Info Not Found!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notices deleted successfully.'
        ]);
    }
}
