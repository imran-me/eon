<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Company;
use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SmsTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = SmsTemplate::select('sms_templates.*')
            ->leftJoin('users', 'users.id', '=', 'sms_templates.created_by')
            ->leftJoin('companies', 'companies.id', '=', 'sms_templates.company_id')
            ->orderBy('users.name', 'asc')
            ->orderBy('companies.name', 'asc')
            ->orderBy('sms_templates.name', 'asc');

        if ($request->has('created_by') && !empty($request->created_by)) {
            $query->where('sms_templates.created_by', $request->created_by);
        }

        if ($request->has('company_id') && !empty($request->company_id)) {
            $query->where('sms_templates.company_id', $request->company_id);
        }

        if ($request->filled('name')) {
            $query->where('sms_templates.name', $request->name);
        }

        if ($request->filled('status')) {
            $query->whereDate('sms_templates.status', $request->status);
        }

        $datas = $query->paginate(20);        
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $companies = Company::orderBy('name')->get();

        return view('sms_templates.index', compact(
            'datas',
            'users',
            'companies'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('sms_templates.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    function extractVariablesFromTemplate($template)
    {
        preg_match_all('/{{\s*(\w+)\s*}}/', $template, $matches);
        return array_unique($matches[1]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|unique:sms_templates,name',
            'title' => 'required',          
            'template' => 'required',          
            'variables' => 'required'          
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

        // Step 1: Parse input variables
        $inputVars = collect(explode(',', $request->variables))
            ->map(fn($v) => trim($v))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Step 2: Validate each variable
        $invalidVars = array_filter($inputVars, function ($var) {
            return !preg_match('/^[a-zA-Z0-9_]+$/', $var); // only letters, numbers, underscores
        });

        if (!empty($invalidVars)) {
            $message = 'Invalid variables found: ' . implode(', ', $invalidVars) . '. Only letters, numbers, and underscores are allowed.';

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ]);
            }

            return back()->with('error', $message)->withInput();
        }

        // Step 3: Continue storing after validation
        $allVars = array_unique(array_merge($this->extractVariablesFromTemplate($request->template), $inputVars));

        $data = SmsTemplate::create([
            'created_by' => auth()->id(),
            'company_id' => $request->company_id,
            'name'       => $request->name,
            'title'      => $request->title,
            'template'   => $request->template,   
            'variables'  => !empty($allVars) ? json_encode($allVars) : null,
            'status'     => $request->status ? 1 : 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.sms_templates.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('sms_templates.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $id = $request->id;
        $data = SmsTemplate::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|unique:sms_templates,name,' . $data->id,
            'title' => 'required',
            'template' => 'required',
            'variables' => 'required'
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

        // Step 1: Parse input variables
        $inputVars = collect(explode(',', $request->variables))
            ->map(fn($v) => trim($v))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Step 2: Validate each variable
        $invalidVars = array_filter($inputVars, function ($var) {
            return !preg_match('/^[a-zA-Z0-9_]+$/', $var); // only letters, numbers, underscores
        });

        if (!empty($invalidVars)) {
            $message = 'Invalid variables found: ' . implode(', ', $invalidVars) . '. Only letters, numbers, and underscores are allowed.';

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ]);
            }

            return back()->with('error', $message)->withInput();
        }

        // Step 3: Continue storing after validation
        $allVars = array_unique(array_merge($this->extractVariablesFromTemplate($request->template), $inputVars));

        $data->update([            
            'name'       => $request->name,
            'title'      => $request->title,
            'template'   => $request->template,  
            'variables'  => !empty($allVars) ? json_encode($allVars) : null,
            'status'     => $request->status ? 1 : 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data
            ]);
        }

        return redirect()->back()->with('success', 'Data updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = SmsTemplate::find($request->item_id);
            if ($item) {
                $item->delete();
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
