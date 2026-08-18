<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Models\Company;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Models\State;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller
{
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
        // $totalCompanies   = Company::count();
        // $activeCompanies  = Company::where('status', 1)->count();
        // $inactiveCompanies = Company::where('status', 0)->count();

        return response()->json([
            'success' => true,
            'message' => 'Companies retrieved successfully.',
            'data' => $datas,
        ]);
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
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        // Create
        $data = Company::updateOrCreate([
            'name' => $request->name,
            'email' => $request->email,
            'phone'   => $request->phone,
            'attendance_device_serial_no' => $request->attendance_device_serial_no
        ],[
            'address' => $request->address,
            'website' => $request->website,
            'status' => $request->status ? 1 : 0
        ]);

        $logo = $request->file('logo');
        if ($logo) {
            $logo_name = uniqid() . '.' . strtolower($logo->getClientOriginalExtension());
            $upload_path = 'image/company/';
            if (!file_exists(public_path($upload_path))) {
                mkdir(public_path($upload_path), 0777, true);
            }
            $success = $logo->move(public_path($upload_path), $logo_name);
            if ($success) {
                if (!empty($data->logo) && file_exists(public_path($data->logo))) {
                    unlink(public_path($data->logo));
                }
                $data->logo = $upload_path . $logo_name;
            }
        }
        $data->save();

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully.',
            'data' => $data
        ]);
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
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }
        try {
            // Handle logo upload
            if ($request->hasFile('logo')) {
                $upload_path = 'image/company/';
                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }

                // Delete old logo
                if (!empty($company->logo) && file_exists(public_path($company->logo))) {
                    unlink(public_path($company->logo));
                }

                $file = $request->file('logo');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($upload_path), $filename);
                $company->logo = $upload_path . $filename;
            }

            // Update basic info
            $company->update([
                'name'    => $request->name,
                'email'   => $request->email ?? null,
                'phone'   => $request->phone ?? null,
                'website' => $request->website ?? null,
                'address' => $request->address ?? null,
                'attendance_device_serial_no' => $request->attendance_device_serial_no ?? null,
                'status'  => $request->has('status') ? 1 : 0,
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
                    'message' => 'Company not found!'
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
            'message' => 'Company deleted successfully.'
        ]);
    }
}
