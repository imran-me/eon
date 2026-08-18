<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Company;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Models\State;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CompanyController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view company setting', only: ['index']),
            new Middleware('permission:create company setting', only: ['create', 'store']),
            new Middleware('permission:edit company setting', only: ['edit', 'update']),
            new Middleware('permission:delete company setting', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Company::query()->orderBy('name', 'asc');

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->has('name') && !empty($request->name)) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        if ($request->has('email') && !empty($request->email)) {
            $query->where('email', 'like', '%'.$request->email.'%');
        }
        if ($request->has('phone') && !empty($request->phone)) {
            $query->where('phone', 'like', '%'.$request->phone.'%');
        }
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Paginate
        $datas = $query->paginate(30);

        // Stats
        $totalCompanies   = Company::count();
        $activeCompanies  = Company::where('status', 1)->count();
        $inactiveCompanies = Company::where('status', 0)->count();

        return view('company.index', compact(
            'datas',
            'totalCompanies',
            'activeCompanies',
            'inactiveCompanies'
        ));
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('company.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255|unique:companies,name',
            'email' => 'required|email|max:255|unique:companies,email',
            'phone' => 'required|string|max:255|unique:companies,phone',
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
        $data = Company::updateOrCreate([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'attendance_device_serial_no' => $request->attendance_device_serial_no,
            'order' => $request->company_order,
        ],[
            'short_name' => $request->short_name ?? null,
            'address'    => $request->address,
            'website'    => $request->website,
            'status'     => $request->status ? 1 : 0,
        ]);

        $upload_path = 'image/company/';
        if (!file_exists(public_path($upload_path))) {
            mkdir(public_path($upload_path), 0777, true);
        }

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logo_name = uniqid() . '.' . strtolower($logo->getClientOriginalExtension());
            if ($logo->move(public_path($upload_path), $logo_name)) {
                if (!empty($data->logo) && file_exists(public_path($data->logo))) {
                    unlink(public_path($data->logo));
                }
                $data->logo = $upload_path . $logo_name;
            }
        }

        if ($request->hasFile('icon')) {
            $icon = $request->file('icon');
            $icon_name = 'icon_' . uniqid() . '.' . strtolower($icon->getClientOriginalExtension());
            if ($icon->move(public_path($upload_path), $icon_name)) {
                if (!empty($data->icon) && file_exists(public_path($data->icon))) {
                    unlink(public_path($data->icon));
                }
                $data->icon = $upload_path . $icon_name;
            }
        }

        $data->save();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.company-settings.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('company.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $role)
    {
        $company = Company::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:companies,name,' . $company->id,
            'email'    => 'required|email|max:255|unique:companies,email,' . $company->id,
            'phone'    => 'required|string|max:255|unique:companies,phone,' . $company->id,
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
            $upload_path = 'image/company/';
            if (!file_exists(public_path($upload_path))) {
                mkdir(public_path($upload_path), 0777, true);
            }

            if ($request->hasFile('logo')) {
                if (!empty($company->logo) && file_exists(public_path($company->logo))) {
                    unlink(public_path($company->logo));
                }
                $file = $request->file('logo');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($upload_path), $filename);
                $company->logo = $upload_path . $filename;
            }

            if ($request->hasFile('icon')) {
                if (!empty($company->icon) && file_exists(public_path($company->icon))) {
                    unlink(public_path($company->icon));
                }
                $file = $request->file('icon');
                $filename = 'icon_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($upload_path), $filename);
                $company->icon = $upload_path . $filename;
            }

            // Update basic info
            $company->update([
                'name'       => $request->name,
                'short_name' => $request->short_name ?? null,
                'email'      => $request->email ?? null,
                'phone'      => $request->phone ?? null,
                'website'    => $request->website ?? null,
                'address'    => $request->address ?? null,
                'order'      => $request->company_order ?? 0,
                'attendance_device_serial_no' => $request->attendance_device_serial_no ?? null,
                'status'     => $request->has('status') ? 1 : 0,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully.',
            'data' => $company,
        ]);
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
            $data = Company::find($request->item_id);
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

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data deleted successfully.'
            ]);
        }

        return redirect('/super-admin/airport')->with('success', 'Data deleted successfully.');
    }
}
