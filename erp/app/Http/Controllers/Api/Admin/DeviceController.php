<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DeviceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $devices = DeviceSetting::all();
            $companies = Company::orderBy('name')->get();

            return response()->json([
                'success' => true,
                'message' => 'Devices retrieved successfully.',
                'data' => $devices,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => $err->getMessage()
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // try {
        $device = new DeviceSetting();
        $device->device_serial_no = $request->input('device_serial_no');
        $device->name = $request->input('name');
        $device->device_location = $request->input('device_location');
        $device->is_active = $request->input('is_active', true);
        $device->save();

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $device
        ]);

        // } catch (\Exception $err) {
        //     return redirect()->back()->with('error', $err->getMessage());
        // }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, string $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_serial_no' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('device_settings', 'device_serial_no')->ignore($request->id),
                ],
                'name' => 'required|string|max:255',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }
            $device = DeviceSetting::findOrFail($request->id);
            $device->device_serial_no = $request->input('device_serial_no');
            $device->name = $request->input('name');
            $device->device_location = $request->input('device_location');
            $device->is_active = $request->input('is_active', true);
            $device->save();

            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $device
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => $err->getMessage()
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
