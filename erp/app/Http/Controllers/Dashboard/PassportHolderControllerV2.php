<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class PassportHolderControllerV2 extends Controller
{
    private const TYPES = ['general', 'visa', 'ticket', 'contract_flight', 'contract_file'];

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $query = PassportHolder::select('passport_holders.*')
            ->leftJoin('passport_holder_categories', 'passport_holder_categories.id', '=', 'passport_holders.category_id')
            ->orderBy('passport_holder_categories.name', 'asc')
            ->orderBy('passport_holders.name', 'asc');

        if ($request->has('category_id') && ! empty($request->category_id)) {
            $query->where('passport_holders.category_id', $request->category_id);
        }

        // Part of the name or the passport number (owner, 2026-10-05: "AFRIDE"
        // and "A07533425" both found nothing — this matched the exact full name only).
        if ($request->filled('name')) {
            $term = '%'.addcslashes(trim($request->name), '%_\\').'%';
            $query->where(fn ($q) => $q->where('passport_holders.name', 'like', $term)
                ->orWhere('passport_holders.passport_no', 'like', $term));
        }

        if ($request->filled('status')) {
            $query->whereDate('passport_holders.status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('passport_holders.type', $request->type);
        }

        $datas = $query->paginate(20);
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $categories = PassportHolderCategory::orderBy('name')->get();

        return view('passport-holder.index', compact(
            'datas',
            'users',
            'categories'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('passport-holder.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        // Contract File's quick add asks for the name only (owner, 2026-10-26); every other
        // screen keeps nationality, category and type required.
        $nameOnly = $request->input('source') === 'contract_file';
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'passport_no' => 'nullable|string|max:50|unique:passport_holders,passport_no',
            'nationality' => ($nameOnly ? 'nullable' : 'required').'|string|max:100',
            'phone' => 'nullable|string|max:20',
            // 'date_of_birth'  => 'required|date',
            // 'issue_date'     => 'required|date',
            // 'expiry_date'    => 'required|date|after_or_equal:issue_date',
            'category_id' => ($nameOnly ? 'nullable' : 'required').'|exists:passport_holder_categories,id',
            'type' => ($nameOnly ? 'nullable' : 'required').'|in:'.implode(',', self::TYPES),
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = PassportHolder::create([
            'name' => $request->name,
            'passport_no' => trim((string) $request->passport_no) !== '' ? strtoupper(trim($request->passport_no)) : null,
            'nationality' => $request->nationality ?? '',   // NOT NULL column; blank only on a name-only add
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'issue_date' => $request->issue_date,
            'expiry_date' => $request->expiry_date,
            'category_id' => $request->category_id,
            'type' => $request->type ?: 'contract_file',     // only a name-only add can arrive without one
            'created_by' => auth()->id(),
            'status' => $request->status ? 1 : 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data,
            ]);
        }

        return redirect()->route('role.passport-holder.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('passport-holder.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $id = $request->id;
        $data = PassportHolder::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!',
            ]);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'passport_no' => 'nullable|string|max:50|unique:passport_holders,passport_no,'.$data->id,
            'nationality' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            // 'date_of_birth'  => 'required|date',
            // 'issue_date'     => 'required|date',
            // 'expiry_date'    => 'required|date|after_or_equal:issue_date',
            'category_id' => 'required|exists:passport_holder_categories,id',
            'type' => 'required|in:'.implode(',', self::TYPES),
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data->update([
            'name' => $request->name,
            'passport_no' => trim((string) $request->passport_no) !== '' ? strtoupper(trim($request->passport_no)) : null,
            'nationality' => $request->nationality,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'issue_date' => $request->issue_date,
            'expiry_date' => $request->expiry_date,
            'category_id' => $request->category_id,
            'type' => $request->type,
            'status' => $request->status ? 1 : 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data,
            ]);
        }

        return redirect()->back()->with('success', 'Data updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = PassportHolder::find($request->item_id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!',
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.',
        ]);
    }

    public function getEmployeeSalary(Request $request)
    {
        try {
            $data = PassportHolder::where('user_id', $request->user_id)->orderBy('id', 'ASC')->get();
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Data Found Successfully.',
        ]);
    }
}
