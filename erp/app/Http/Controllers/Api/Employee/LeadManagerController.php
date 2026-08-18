<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadAirTicket;
use App\Models\LeadSource;
use App\Models\LeadStatusHistory;
use App\Models\LeadVisa;
use App\Models\LeadVisaDocument;
use App\Models\VisaRequiredDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadManagerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Lead::with('leadSource','customer','assignedEmployee')->orderBy('name', 'asc');
        // if ($request->has('name') && !empty($request->name)) {
        
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }
        if ($request->filled('lead_source_id')) {
            $query->where('lead_source_id', $request->lead_source_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('lead_type')) {
            $query->where('lead_type', $request->lead_type);
        }

        $datas = $query->with(['airTicket', 'visa.country', 'visaDocuments'])->paginate(30);

        // $leadSources = LeadSource::where('is_active', 1)->orderBy('name', 'asc')->get();
        // $employees   = User::role('employee')->get();
        // $customers   = Customer::where('is_active', 1)->orderBy('name', 'asc')->get();
        // $countries   = Country::orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Lead list retrieved successfully.',
            'data'    => $datas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:255|unique:leads,name',
            'email'             => 'nullable|email|max:255|unique:leads,email',
            'phone'             => 'required|string|min:10',
            'lead_type'         => 'required|string|in:air_ticket,visa,software,other',
            'lead_source_id'    => 'required|integer|exists:lead_sources,id',
            'status'            => 'required|string|in:new,contacted,qualified,proposal_sent,negotiation,won,lost',
            'assigned_to'       => 'required|integer|exists:users,id',
            'customer_id'       => 'nullable|integer|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $data = Lead::updateOrCreate(['name' => $request->name], [
            'lead_type'      => $request->lead_type,
            'service_category' => $request->service_category,
            'lead_source_id' => $request->lead_source_id,
            'email'          => $request->email,
            'phone'          => $request->phone,
            'status'         => $request->status,
            'assigned_to'    => $request->assigned_to,
            'customer_id'    => $request->customer_id,
            'notes'          => $request->notes,
        ]);

        if ($data->wasRecentlyCreated) {
            $this->logLeadStatusHistory($data->id, null, $request->status);
        }

        // Save air ticket sub-data
        if ($request->lead_type === 'air_ticket') {
            LeadAirTicket::updateOrCreate(['lead_id' => $data->id], [
                'route_from'        => strtoupper($request->route_from),
                'route_to'          => strtoupper($request->route_to),
                'travel_date'       => $request->travel_date,
                'pax_count'         => $request->pax_count ?? 1,
                'ticket_class'      => $request->ticket_class ?? 'economy',
                'preferred_airline' => $request->preferred_airline,
                'quoted_price'      => $request->quoted_price,
                'payment_status'    => $request->at_payment_status ?? 'pending',
            ]);
        }

        // Save visa sub-data
        if ($request->lead_type === 'visa') {
            $visaType = $request->visa_type ?? 'tourist';
            LeadVisa::updateOrCreate(['lead_id' => $data->id], [
                'country_id'      => $request->country_id,
                'visa_type'       => $visaType,
                'travel_date'     => $request->visa_travel_date,
                'document_status' => $request->document_status ?? 'pending',
                'payment_status'  => $request->visa_payment_status ?? 'pending',
                'remarks'         => $request->visa_remarks,
            ]);

            if ($data->wasRecentlyCreated) {
                $this->populateVisaDocumentChecklist($data->id, $visaType);
            } else {
                // If visa_type changed, refresh the checklist
                $existing = LeadVisaDocument::where('lead_id', $data->id)->first();
                if (!$existing) {
                    $this->populateVisaDocumentChecklist($data->id, $visaType);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully.',
            'data'    => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = Lead::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255|unique:leads,name,' . $data->id,
            'email'          => 'nullable|email|max:255|unique:leads,email,' . $data->id,
            'phone'          => 'required|string|min:10',
            'lead_type'      => 'required|string|in:air_ticket,visa,software,other',
            'lead_source_id' => 'required|integer|exists:lead_sources,id',
            'assigned_to'    => 'required|integer|exists:users,id',
            'status'         => 'required|string|in:new,contacted,qualified,proposal_sent,negotiation,won,lost',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        try {
            $oldStatus = $data->status;

            $data->update([
                'lead_type'        => $request->lead_type,
                'service_category' => $request->service_category,
                'name'             => $request->name,
                'lead_source_id'   => $request->lead_source_id,
                'email'            => $request->email,
                'phone'            => $request->phone,
                'status'           => $request->status,
                'assigned_to'      => $request->assigned_to,
                'customer_id'      => $request->customer_id,
                'notes'            => $request->notes,
            ]);

            if ($oldStatus !== $request->status) {
                $this->logLeadStatusHistory($data->id, $oldStatus, $request->status);
            }

            // Update air ticket sub-data
            if ($request->lead_type === 'air_ticket') {
                LeadAirTicket::updateOrCreate(['lead_id' => $data->id], [
                    'route_from'        => strtoupper($request->route_from),
                    'route_to'          => strtoupper($request->route_to),
                    'travel_date'       => $request->travel_date,
                    'pax_count'         => $request->pax_count ?? 1,
                    'ticket_class'      => $request->ticket_class ?? 'economy',
                    'preferred_airline' => $request->preferred_airline,
                    'quoted_price'      => $request->quoted_price,
                    'payment_status'    => $request->at_payment_status ?? 'pending',
                ]);
            }

            // Update visa sub-data
            if ($request->lead_type === 'visa') {
                $visaType = $request->visa_type ?? 'tourist';
                $oldVisa  = $data->visa;
                LeadVisa::updateOrCreate(['lead_id' => $data->id], [
                    'country_id'      => $request->country_id,
                    'visa_type'       => $visaType,
                    'travel_date'     => $request->visa_travel_date,
                    'document_status' => $request->document_status ?? 'pending',
                    'payment_status'  => $request->visa_payment_status ?? 'pending',
                    'remarks'         => $request->visa_remarks,
                ]);

                $noChecklist = !LeadVisaDocument::where('lead_id', $data->id)->exists();
                $typeChanged = $oldVisa && $oldVisa->visa_type !== $visaType;

                if ($noChecklist || $typeChanged) {
                    LeadVisaDocument::where('lead_id', $data->id)->delete();
                    $this->populateVisaDocumentChecklist($data->id, $visaType);
                }
            }

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully.',
            'data'    => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $data = Lead::find($id);
        if ($data) {
            $data->delete();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }

    /**
     * Return the document checklist for a visa lead (AJAX)
     */
    public function getVisaDocuments($lead_id)
    {
        $lead = Lead::with('visa.country')->findOrFail($lead_id);

        if ($lead->lead_type !== 'visa') {
            return response()->json([
                'success' => false,
                'message' => 'Not a visa lead.'
            ], 400);
        }

        // Auto-create checklist if missing
        if (!LeadVisaDocument::where('lead_id', $lead_id)->exists() && $lead->visa) {
            $this->populateVisaDocumentChecklist($lead_id, $lead->visa->visa_type);
        }

        $docs = LeadVisaDocument::where('lead_id', $lead_id)->orderBy('sort_order')->get();

        return response()->json([
            'success'  => true,
            'message'  => 'Documents retrieved successfully.',
            'data'     => $docs
        ]);
    }

    /**
     * Toggle a single document's collected status (AJAX)
     */
    public function toggleVisaDocument($docId)
    {
        $doc = LeadVisaDocument::findOrFail($docId);
        $doc->is_collected  = !$doc->is_collected;
        $doc->collected_at  = $doc->is_collected ? now() : null;
        $doc->save();

        // Auto-update overall document_status on lead_visas
        $lead = Lead::with('visa')->find($doc->lead_id);
        if ($lead && $lead->visa) {
            $all   = LeadVisaDocument::where('lead_id', $doc->lead_id)->count();
            $done  = LeadVisaDocument::where('lead_id', $doc->lead_id)->where('is_collected', true)->count();
            $mandMissing = LeadVisaDocument::where('lead_id', $doc->lead_id)
                ->where('is_mandatory', true)->where('is_collected', false)->count();

            $status = 'pending';
            if ($done === $all && $all > 0)   $status = 'complete';
            elseif ($done > 0 && $mandMissing === 0) $status = 'complete';
            elseif ($done > 0)               $status = 'collected';

            $lead->visa->update(['document_status' => $status]);
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Document status updated.',
            'data' => [
                'is_collected' => $doc->is_collected,
                'document_status' => $lead?->visa?->document_status,
            ]
        ]);
    }

    /**
     * Populate document checklist from master table
     */
    private function populateVisaDocumentChecklist(int $leadId, string $visaType): void
    {
        $required = VisaRequiredDocument::getForType($visaType);

        if ($required->isEmpty()) {
            // Fallback: insert basic passport + photo
            LeadVisaDocument::insert([
                ['lead_id' => $leadId, 'document_name' => 'Valid Passport', 'is_mandatory' => true,  'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['lead_id' => $leadId, 'document_name' => 'Passport Size Photo', 'is_mandatory' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ]);
            return;
        }

        $rows = $required->map(fn($r) => [
            'lead_id'       => $leadId,
            'document_name' => $r->document_name,
            'is_mandatory'  => $r->is_mandatory,
            'is_collected'  => false,
            'sort_order'    => $r->sort_order,
            'created_at'    => now(),
            'updated_at'    => now(),
        ])->toArray();

        LeadVisaDocument::insert($rows);
    }

    /**
     * Helper method to log lead status changes
     */
    private function logLeadStatusHistory($leadId, $oldStatus, $newStatus)
    {
        LeadStatusHistory::create([
            'lead_id' => $leadId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => auth()->id(),
        ]);
    }

    public function getStatusHistory(string $id)
    {
        $lead = Lead::findOrFail($id);
        $histories = $lead->statusHistories()
            ->with('changedBy:id,name')
            ->get()
            ->map(function($history){
                return [
                    'id' => $history->id,
                    'old_status' => $history->old_status,
                    'new_status' => $history->new_status,
                    'changed_by_name' => $history->changedBy ? $history->changedBy->name : 'Unknown',
                    'created_at' => $history->created_at->format('M d, Y h:i A'),
                    'time_ago'   => $history->created_at->diffForHumans(),
                ];
            });
        return response()->json([
            'success' => true,
            'message' => 'Status history retrieved successfully.',
            'data'    => $histories
        ]);
    }
}
