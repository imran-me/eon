<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\OtherServiceType;
use App\Models\OtherVisaService;
use App\Models\PassportHolder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OtherVisaServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = OtherVisaService::with(['passportHolder', 'visaProcess', 'serviceType', 'country', 'assignedOfficer'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('service_code', 'like', "%{$search}%")
                  ->orWhereHas('passportHolder', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('other_service_type_id')) {
            $query->where('other_service_type_id', $request->other_service_type_id);
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->paginate(20);

        $passportHolders = PassportHolder::orderBy('name')->get();
        $serviceTypes    = OtherServiceType::where('is_active', 1)->orderBy('name')->get();
        $countries       = Country::orderBy('name')->get();
        $officers        = User::orderBy('name')->role('visa')->get();

        return view('other-visa-services.index', compact(
            'datas',
            'passportHolders',
            'serviceTypes',
            'countries',
            'officers'
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'passport_holder_id'    => 'required|exists:passport_holders,id',
            'visa_process_id'       => 'nullable|exists:visa_processes,id',
            'other_service_type_id' => 'required|exists:other_service_types,id',
            'country_id'            => 'nullable|exists:countries,id',
            'assigned_officer_id'   => 'nullable|exists:users,id',
            'sale_price'            => 'required|numeric|min:0',
            'cost_price'            => 'required|numeric|min:0',
            'deadline'              => 'nullable|date',
            'status'                => 'required|in:pending,in_progress,done,overdue,cancelled',
            'notes'                 => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::transaction(function () use ($request) {
                OtherVisaService::create([
                    'service_code'           => OtherVisaService::nextServiceCode(),
                    'passport_holder_id'     => $request->passport_holder_id,
                    'visa_process_id'        => $request->visa_process_id,
                    'other_service_type_id'  => $request->other_service_type_id,
                    'country_id'             => $request->country_id,
                    'assigned_officer_id'    => $request->assigned_officer_id,
                    'cost_price'             => $request->cost_price,
                    'sale_price'             => $request->sale_price,
                    'deadline'               => $request->deadline,
                    'status'                 => $request->status,
                    'notes'                  => $request->notes,
                    'is_billable'            => $request->boolean('is_billable'),
                    'created_by'             => Auth::id(),
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
            'message' => 'Service created successfully.',
        ]);
    }

    public function update(Request $request, $role, string $id)
    {
        $data = OtherVisaService::find($id);
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'passport_holder_id'    => 'required|exists:passport_holders,id',
            'visa_process_id'       => 'nullable|exists:visa_processes,id',
            'other_service_type_id' => 'required|exists:other_service_types,id',
            'country_id'            => 'nullable|exists:countries,id',
            'assigned_officer_id'   => 'nullable|exists:users,id',
            'sale_price'            => 'required|numeric|min:0',
            'cost_price'            => 'required|numeric|min:0',
            'deadline'              => 'nullable|date',
            'status'                => 'required|in:pending,in_progress,done,overdue,cancelled',
            'notes'                 => 'nullable|string',
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
                    'passport_holder_id'    => $request->passport_holder_id,
                    'visa_process_id'       => $request->visa_process_id,
                    'other_service_type_id' => $request->other_service_type_id,
                    'country_id'            => $request->country_id,
                    'assigned_officer_id'   => $request->assigned_officer_id,
                    'cost_price'            => $request->cost_price,
                    'sale_price'            => $request->sale_price,
                    'deadline'              => $request->deadline,
                    'status'                => $request->status,
                    'notes'                 => $request->notes,
                    'is_billable'           => $request->boolean('is_billable'),
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
            'message' => 'Service updated successfully.',
        ]);
    }

    public function destroy(Request $request, $role, string $id)
    {
        try {
            $item = OtherVisaService::with('saleItem')->find($request->item_id ?? $id);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!',
                ]);
            }

            if ($item->saleItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete this service, it is already bundled into a sale voucher!',
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
            'message' => 'Service deleted successfully.',
        ]);
    }
}
