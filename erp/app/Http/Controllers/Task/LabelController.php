<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LabelController extends Controller
{
    private const PRESET_COLORS = ['gray', 'blue', 'purple', 'green', 'yellow', 'red', 'indigo', 'pink', 'orange', 'teal'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Label::with('project');
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        
        $datas = $query->orderBy('name', 'asc')->paginate(30);

        $projects = Project::orderBy('project_name', 'asc')->get();

        return view('task-module.labels.index', compact(
            'datas',
            'projects',
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id'    => 'required|integer|exists:projects,id',
            'name'          => [
                'required', 'string', 'max:255',
                Rule::unique('labels', 'name')->where('project_id', $request->project_id),
            ],
            'color'         => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (!in_array($value, self::PRESET_COLORS, true) && !preg_match('/^#[A-Fa-f0-9]{6}$/', $value)) {
                    $fail('The color must be a preset color or a valid hex color.');
                }
            }],
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

        // Create
        $data = Label::create([
            'project_id' => $request->project_id,
            'name' => $request->name,
            'color' => $request->color ?? 'blue'
        ]);

        // Note: Labels are attached to tasks in TaskController via the labels()->sync() method

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Label created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.labels.index', ['role' => \Str::slug(\Auth::user()->getRoleNames()->first())])->with('success', 'Label created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $label = Label::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|integer|exists:projects,id',
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('labels', 'name')->where('project_id', $request->project_id)->ignore($label->id),
            ],
            'color' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (!in_array($value, self::PRESET_COLORS, true) && !preg_match('/^#[A-Fa-f0-9]{6}$/', $value)) {
                    $fail('The color must be a preset color or a valid hex color.');
                }
            }],
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

        // Update label
        $label->update([
            'project_id' => $request->project_id,
            'name' => $request->name,
            'color' => $request->color ?? $label->color ?? 'blue'
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Label updated successfully.',
                'data' => $label
            ]);
        }

        return redirect()->route('role.labels.index', ['role' => \Str::slug(\Auth::user()->getRoleNames()->first())])->with('success', 'Label updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $data = Label::find($request->item_id);
            if ($data) {
                $data->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
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
            'message' => 'Data deleted successfully.'
        ]);
    }
}
