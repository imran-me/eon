<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\OtherServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OtherServiceTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = OtherServiceType::orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $datas = $query->paginate(20);

        return view('other-service-types.index', compact('datas'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:other_service_types,name',
            'icon'        => 'nullable|string|max:50',
            'color'       => 'nullable|string|max:20',
            'bg_color'    => 'nullable|string|max:20',
            'default_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::transaction(function () use ($request) {
                OtherServiceType::create([
                    'name'        => $request->name,
                    'icon'        => $request->icon,
                    'color'       => $request->color,
                    'bg_color'    => $request->bg_color,
                    'default_fee' => $request->default_fee,
                    'is_active'   => $request->boolean('is_active'),
                ]);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Service type created successfully.',
        ]);
    }

    public function update(Request $request, $role, string $id)
    {
        $data = OtherServiceType::find($id);
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:other_service_types,name,' . $data->id,
            'icon'        => 'nullable|string|max:50',
            'color'       => 'nullable|string|max:20',
            'bg_color'    => 'nullable|string|max:20',
            'default_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::transaction(function () use ($data, $request) {
                $data->update([
                    'name'        => $request->name,
                    'icon'        => $request->icon,
                    'color'       => $request->color,
                    'bg_color'    => $request->bg_color,
                    'default_fee' => $request->default_fee,
                    'is_active'   => $request->boolean('is_active'),
                ]);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Service type updated successfully.',
        ]);
    }

    public function destroy(Request $request, $role, string $id)
    {
        try {
            $item = OtherServiceType::with('services')->find($request->item_id ?? $id);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!',
                ]);
            }

            if ($item->services()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete this type, it is already used by other services!',
                ]);
            }

            $item->delete();
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Service type deleted successfully.',
        ]);
    }
}
