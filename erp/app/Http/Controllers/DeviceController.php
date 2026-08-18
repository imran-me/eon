<?php

namespace App\Http\Controllers;

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
            return view('attend-device.index', compact('devices', 'companies'));
        } catch (\Exception $err) {
            return response()->json(['error' => $err->getMessage()], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $device
            ]);
        }

        return redirect()->route('role.device-settings.index')->with('success', 'Data created successfully.');
        // } catch (\Exception $err) {
        //     return redirect()->back()->with('error', $err->getMessage());
        // }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // try {
        $device = DeviceSetting::findOrFail($id);
        return view('device.edit-modal', compact('device'));
        // } catch (\Exception $err) {
        //     return response()->json(['error' => $err->getMessage()], 500);
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

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $device
            ]);
        }

        return redirect()->route('role.device-settings.index')->with('success', 'Data updated successfully.');
        } catch (\Exception $err) {
            return redirect()->back()->with('error', $err->getMessage());
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
