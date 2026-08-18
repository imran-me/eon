<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ProjectDepartment;
use App\Models\ProjectFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequireTypeController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id ?? 1;

        $datas = ProjectDepartment::where('company_id', $companyId)
            ->orderBy('name')
            ->paginate(30);

        return view('projects.require-types.index', compact('datas'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $data = ProjectDepartment::create([
            'company_id' => auth()->user()->company_id ?? 1,
            'name'       => $request->name,
            'status'     => $request->boolean('status', true),
        ]);

        return response()->json(['success' => true, 'message' => 'Require type created.', 'data' => $data]);
    }

    public function update(Request $request, ProjectDepartment $requireType)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $requireType->update([
            'name'   => $request->name,
            'status' => $request->boolean('status', true),
        ]);

        return response()->json(['success' => true, 'message' => 'Require type updated.', 'data' => $requireType]);
    }

    public function destroy(ProjectDepartment $requireType)
    {
        $requireType->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }

    // --- Custom Field Definitions ---

    public function storeField($role, Request $request, ProjectDepartment $requireType)
    {
        $validator = Validator::make($request->all(), [
            'label'      => 'required|string|max:255',
            'type'       => 'required|in:text,number,date,select,textarea',
            'options'    => 'nullable|string',
            'required'   => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $field = ProjectFieldDefinition::create([
            'project_department_id' => $requireType->id,
            'label'                 => $request->label,
            'type'                  => $request->type,
            'options'               => $request->options,
            'required'              => $request->boolean('required', false),
            'sort_order'            => $request->sort_order ?? 0,
            'status'                => true,
        ]);

        return response()->json(['success' => true, 'message' => 'Field added.', 'data' => $field]);
    }

    public function updateField(Request $request, ProjectFieldDefinition $fieldDefinition)
    {
        $validator = Validator::make($request->all(), [
            'label'      => 'required|string|max:255',
            'type'       => 'required|in:text,number,date,select,textarea',
            'options'    => 'nullable|string',
            'required'   => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $fieldDefinition->update([
            'label'      => $request->label,
            'type'       => $request->type,
            'options'    => $request->options,
            'required'   => $request->boolean('required', false),
            'sort_order' => $request->sort_order ?? $fieldDefinition->sort_order,
            'status'     => $request->boolean('status', true),
        ]);

        return response()->json(['success' => true, 'message' => 'Field updated.', 'data' => $fieldDefinition]);
    }

    public function destroyField(ProjectFieldDefinition $fieldDefinition)
    {
        $fieldDefinition->delete();
        return response()->json(['success' => true, 'message' => 'Field deleted.']);
    }

    public function getFields(string $_role, ProjectDepartment $requireType)
    {
        $fields = $requireType->fieldDefinitions()->where('status', true)->get();
        return response()->json(['success' => true, 'data' => $fields]);
    }
}
