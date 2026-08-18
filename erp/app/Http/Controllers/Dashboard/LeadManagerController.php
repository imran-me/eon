<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\LeadAirTicket;
use App\Models\LeadInterior;
use App\Models\LeadSource;
use App\Models\LeadStatusHistory;
use App\Models\LeadVisa;
use App\Models\LeadVisaDocument;
use App\Models\VisaRequiredDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LeadManagerController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view lead manager', only: ['index']),
            new Middleware('permission:create lead manager', only: ['store']),
            new Middleware('permission:edit lead manager', only: ['update']),
            new Middleware('permission:delete lead manager', only: ['destroy']),
        ];
    }
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

        $datas = $query->with(['airTicket', 'visa.country', 'visaDocuments', 'interior', 'project'])->paginate(30);

        $leadSources = LeadSource::where('is_active', 1)->orderBy('name', 'asc')->get();
        $employees   = User::role('employee')->get();
        $customers   = Customer::where('is_active', 1)->orderBy('name', 'asc')->get();
        $countries   = Country::orderBy('name', 'asc')->get();

        return view('lead-manager.index', compact(
            'datas',
            'leadSources',
            'employees',
            'customers',
            'countries'
        ));
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
            'lead_type'         => 'required|string|in:air_ticket,visa,software,interior,other',
            'lead_source_id'    => 'required|integer|exists:lead_sources,id',
            'status'            => 'required|string|in:new,contacted,qualified,proposal_sent,negotiation,won,lost',
            'assigned_to'       => 'required|integer|exists:users,id',
            'customer_id'       => 'nullable|integer|exists:customers,id',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }
            return redirect()->back()->withErrors($validator)->withInput();
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

        // Save interior sub-data
        if ($request->lead_type === 'interior') {
            LeadInterior::updateOrCreate(['lead_id' => $data->id], [
                'property_type'        => $request->property_type ?? 'flat',
                'area_sqft'            => $request->area_sqft,
                'style_preference'     => $request->style_preference,
                'budget_min'           => $request->budget_min,
                'budget_max'           => $request->budget_max,
                'site_visit_date'      => $request->site_visit_date,
                'site_visit_duration'  => $request->site_visit_duration,
                'site_visit_done'      => $request->boolean('site_visit_done'),
                'design_notes'         => $request->design_notes,
            ]);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Lead created successfully.',
                'data'    => $data
            ]);
        }

        return redirect()->route('role.lead-manager.index')->with('success', 'Lead created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function convertToProject(Request $request, $role, $leadId)
    {
        $lead = Lead::findOrFail($leadId);

        if ($lead->status !== 'won') {
            return back()->with('error', 'শুধুমাত্র Won lead কে project এ convert করা যাবে।');
        }

        if ($lead->project()->exists()) {
            return back()->with('error', 'এই lead ইতিমধ্যে project এ convert হয়েছে।');
        }

        $project = Project::create([
            'lead_id'      => $lead->id,
            'customer_id'  => $lead->customer_id,
            'project_name' => $request->input('project_name') ?: $lead->name,
            'status'       => 'in_progress',
            'start_date'   => now()->toDateString(),
            'color'        => 'teal',
            'budget'       => $request->input('budget', 0),
            'description'  => $lead->notes,
        ]);

        return redirect()->route('role.lead-manager.index', ['role' => $request->role])
            ->with('success', '"' . $project->project_name . '" project সফলভাবে তৈরি হয়েছে!');
    }

    public function update(Request $request, string $id)
    {
        $data = Lead::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255|unique:leads,name,' . $data->id,
            'email'          => 'nullable|email|max:255|unique:leads,email,' . $data->id,
            'phone'          => 'required|string|min:10',
            'lead_type'      => 'required|string|in:air_ticket,visa,software,interior,other',
            'lead_source_id' => 'required|integer|exists:lead_sources,id',
            'assigned_to'    => 'required|integer|exists:users,id',
            'status'         => 'required|string|in:new,contacted,qualified,proposal_sent,negotiation,won,lost',
        ]);

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

            // Update interior sub-data
            if ($request->lead_type === 'interior') {
                LeadInterior::updateOrCreate(['lead_id' => $data->id], [
                    'property_type'        => $request->property_type ?? 'flat',
                    'area_sqft'            => $request->area_sqft,
                    'style_preference'     => $request->style_preference,
                    'budget_min'           => $request->budget_min,
                    'budget_max'           => $request->budget_max,
                    'site_visit_date'      => $request->site_visit_date,
                    'site_visit_duration'  => $request->site_visit_duration,
                    'site_visit_done'      => $request->boolean('site_visit_done'),
                    'design_notes'         => $request->design_notes,
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
            'message' => 'Lead updated successfully.',
            'data'    => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $data = Lead::find($request->item_id);
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

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }

    /**
     * Return the document checklist for a visa lead (AJAX)
     */
    public function getVisaDocuments($role, $leadId)
    {
        $lead = Lead::with('visa.country')->findOrFail($leadId);

        if ($lead->lead_type !== 'visa') {
            return response()->json(['success' => false, 'message' => 'Not a visa lead.']);
        }

        // Auto-create checklist if missing
        if (!LeadVisaDocument::where('lead_id', $leadId)->exists() && $lead->visa) {
            $this->populateVisaDocumentChecklist($leadId, $lead->visa->visa_type);
        }

        $docs = LeadVisaDocument::where('lead_id', $leadId)->orderBy('sort_order')->get();

        return response()->json([
            'success'  => true,
            'lead'     => ['id' => $lead->id, 'name' => $lead->name, 'visa_type' => $lead->visa?->visa_type],
            'documents' => $docs,
        ]);
    }

    /**
     * Toggle a single document's collected status (AJAX)
     */
    public function toggleVisaDocument(Request $request, $role, $docId)
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
            'is_collected' => $doc->is_collected,
            'document_status' => $lead?->visa?->document_status,
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

    public function markSiteVisitDone(Request $request, $role, $leadId)
    {
        $interior = \App\Models\LeadInterior::where('lead_id', $leadId)->firstOrFail();
        $interior->update([
            'site_visit_done' => true,
            'site_visit_date' => $interior->site_visit_date ?? now()->toDateString(),
        ]);

        return response()->json(['success' => true]);
    }

    public function getStatusHistory($role, $id){
        try{
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
                'histories' => $histories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
