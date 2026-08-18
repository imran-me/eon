<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Country;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use App\Models\PaymentSchedule;
use App\Models\Portal;
use App\Models\User;
use App\Models\VisaCategory;
use App\Models\VisaProcess;
use App\Models\VisaAttachment;
use App\Models\VisaProcessDocument;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VisaProcessingController extends Controller
{
    use PostsPartyLedger, PostsPurchaseJournal;

    public function docTracker(Request $request)
    {
        $query = VisaProcess::with(['passportHolder', 'country', 'visaCategory', 'processDocuments'])
            ->orderByDesc('id');

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->paginate(25);
        $countries = Country::orderBy('name')->get();
        $standardDocs = VisaProcessDocument::$standardDocs;

        return view('visa-processing.doc-tracker', compact('datas', 'countries', 'standardDocs'));
    }

    public function toggleDocStatus(Request $request, string $_role, int $visaId)
    {
        $docName = $request->input('doc');
        $visa = VisaProcess::findOrFail($visaId);

        $doc = VisaProcessDocument::firstOrNew([
            'visa_process_id' => $visa->id,
            'document_name'   => $docName,
        ]);

        if (!$doc->exists) {
            $doc->is_mandatory = true;
            $doc->sort_order   = 0;
        }

        if ($doc->status === 'received') {
            $doc->status      = 'pending';
            $doc->received_at = null;
        } elseif ($doc->status === 'pending') {
            $doc->status      = 'not_required';
            $doc->received_at = null;
        } else {
            $doc->status      = 'received';
            $doc->received_at = now();
        }

        $doc->save();

        return response()->json(['success' => true, 'status' => $doc->status]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = VisaProcess::with(['passportHolder', 'country', 'visaCategory', 'visaAttachments', 'assignedOfficer', 'vendor', 'paymentSchedules'])
            ->orderByDesc('id');

        if ($request->filled('passport_holder_id')) {
            $query->where('passport_holder_id', $request->passport_holder_id);
        }
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }
        if ($request->filled('visa_category_id')) {
            $query->where('visa_category_id', $request->visa_category_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->get();
        $grouped = $datas->groupBy('status');

        $passportHolders = PassportHolder::orderBy('name')->get();
        // $countries = Country::orderBy('name')->get();
        // $visaCategories = VisaCategory::orderBy('name')->get();
        
        $countries = Country::whereHas('visaCategories', function($query) {
            $query->whereNotNull('country_id');
        })->orderBy('name')->get();

        $visaCategories = VisaCategory::with('country')->whereNotNull('country_id')->orderBy('name')->get();
        
        $officers = User::orderBy('name')->role('visa')->get();
        $vendors = User::orderBy('name')->role('vendor')->get();
        $banks = Bank::where('status', 1)->get();
        $portals = Portal::where('status', 'active')->orderBy('name')->get();
        $nextApplicationId = $this->generateApplicationId();

        $visaCategoriesJson = $visaCategories->map(function ($vc) {
            return [
                'id'                  => $vc->id,
                'name'                => $vc->name,
                'country_id'          => $vc->country_id,
                'visa_type'           => $vc->visa_type,
                'costing_price'       => (float) $vc->costing_price,
                'sale_price'          => (float) $vc->sale_price,
                'avg_processing_days' => $vc->avg_processing_days,
            ];
        });

        $passportHoldersJson = $passportHolders->map(function ($ph) {
            return [
                'id'          => $ph->id,
                'passport_no' => $ph->passport_no,
                'phone'       => $ph->phone,
                'nationality' => $ph->nationality,
            ];
        });

        $phCategories = PassportHolderCategory::orderBy('name')->get();

        return view('visa-processing.index', compact('datas', 'grouped', 'passportHolders', 'countries', 'visaCategories', 'officers', 'vendors', 'banks', 'portals', 'nextApplicationId', 'visaCategoriesJson', 'passportHoldersJson', 'phCategories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(string $role)
    {
        return redirect()->route('role.visa.index', ['role' => $role, 'open_create' => 1]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'passport_holder_id'  => 'required|exists:passport_holders,id',
            'vendor_id'           => 'nullable|exists:users,id',
            'portal_id'           => 'nullable|exists:portals,id',
            'cost_bank_id'        => 'nullable|exists:banks,id',
            'cost_paid_amount'    => 'nullable|numeric|min:0',
            'purchase_date'       => 'nullable|date',
            'country_id'          => 'required|exists:countries,id',
            'visa_category_id'    => 'required|exists:visa_categories,id',
            'duration'            => 'nullable|string|max:50',
            'travel_date'         => 'nullable|date',
            'costing_price'       => 'nullable|numeric|min:0',
            'sale_price'          => 'nullable|numeric|min:0',
            'advance_received'    => 'nullable|numeric|min:0',
            'payable_date'        => 'nullable|date',
            'payment_status'      => 'required|in:pending,partial,paid',
            'assigned_officer_id' => 'nullable|exists:users,id',
            'status'              => 'required|in:pending,received,in_embassy,approved,delivered,rejected',
            'stage'               => 'nullable|string|max:100',
            'remarks'             => 'nullable|string',
            'attachments.*'       => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $visaCategory = VisaCategory::find($request->visa_category_id);

        $data = DB::transaction(function () use ($request, $visaCategory) {
            $visa = VisaProcess::create([
                'application_id'      => $this->generateApplicationId(),
                'passport_holder_id'  => $request->passport_holder_id,
                'vendor_id'           => $request->vendor_id,
                'portal_id'           => $request->portal_id,
                'cost_bank_id'        => $request->cost_bank_id,
                'cost_paid_amount'    => $request->cost_paid_amount ?? 0,
                'purchase_date'       => $request->purchase_date,
                'country_id'          => $request->country_id,
                'visa_category_id'    => $request->visa_category_id,
                'visa_type'           => $visaCategory?->visa_type,
                'duration'            => $request->duration,
                'travel_date'         => $request->travel_date,
                'costing_price'       => $request->costing_price ?? 0,
                'sale_price'          => $request->sale_price ?? 0,
                'advance_received'    => $request->advance_received ?? 0,
                'payable_date'        => $request->payable_date,
                'payment_status'      => $request->payment_status,
                'assigned_officer_id' => $request->assigned_officer_id,
                'status'              => $request->status,
                'stage'               => $request->stage,
                'remarks'             => $request->remarks,
            ]);

            $this->syncPaymentSchedules($visa);

            return $visa;
        });

        // Attach uploaded files if any
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                if ($attachment->isValid()) {
                    $path = $this->storePublicFile(
                        $attachment,
                        'uploads/visa-attachments/' . $data->id
                    );

                    VisaAttachment::create([
                        'visa_process_id' => $data->id,
                        'file_path' => $path,
                        'file_name' => $attachment->getClientOriginalName(),
                    ]);
                }
            }
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data,
            ]);
        }

        return redirect()->route('role.visa.index', ['role' => $role])
            ->with('success', 'Visa processing created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return view('visa-processing.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, string $id)
    {
        $data = VisaProcess::findOrFail($request->id ?? $id);

        $validator = Validator::make($request->all(), [
            'passport_holder_id'  => 'required|exists:passport_holders,id',
            'vendor_id'           => 'nullable|exists:users,id',
            'portal_id'           => 'nullable|exists:portals,id',
            'cost_bank_id'        => 'nullable|exists:banks,id',
            'cost_paid_amount'    => 'nullable|numeric|min:0',
            'purchase_date'       => 'nullable|date',
            'country_id'          => 'required|exists:countries,id',
            'visa_category_id'    => 'required|exists:visa_categories,id',
            'duration'            => 'nullable|string|max:50',
            'travel_date'         => 'nullable|date',
            'costing_price'       => 'nullable|numeric|min:0',
            'sale_price'          => 'nullable|numeric|min:0',
            'advance_received'    => 'nullable|numeric|min:0',
            'payable_date'        => 'nullable|date',
            'payment_status'      => 'required|in:pending,partial,paid',
            'assigned_officer_id' => 'nullable|exists:users,id',
            'status'              => 'required|in:pending,received,in_embassy,approved,delivered,rejected',
            'stage'               => 'nullable|string|max:100',
            'remarks'             => 'nullable|string',
            'attachments.*'       => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $visaCategory = VisaCategory::find($request->visa_category_id);
        $oldStatus    = $data->status;

        DB::transaction(function () use ($request, $data, $visaCategory) {
            $data->update([
                'passport_holder_id'  => $request->passport_holder_id,
                'vendor_id'           => $request->vendor_id,
                'portal_id'           => $request->portal_id,
                'cost_bank_id'        => $request->cost_bank_id,
                'cost_paid_amount'    => $request->cost_paid_amount ?? 0,
                'purchase_date'       => $request->purchase_date,
                'country_id'          => $request->country_id,
                'visa_category_id'    => $request->visa_category_id,
                'visa_type'           => $visaCategory?->visa_type,
                'duration'            => $request->duration,
                'travel_date'         => $request->travel_date,
                'costing_price'       => $request->costing_price ?? 0,
                'sale_price'          => $request->sale_price ?? 0,
                'advance_received'    => $request->advance_received ?? 0,
                'payable_date'        => $request->payable_date,
                'payment_status'      => $request->payment_status,
                'assigned_officer_id' => $request->assigned_officer_id,
                'status'              => $request->status,
                'stage'               => $request->stage,
                'remarks'             => $request->remarks,
            ]);

            $this->syncPaymentSchedules($data);
        });

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                if ($attachment->isValid()) {
                    $path = $this->storePublicFile(
                        $attachment,
                        'uploads/visa-attachments/' . $data->id
                    );

                    VisaAttachment::create([
                        'visa_process_id' => $data->id,
                        'file_path' => $path,
                        'file_name' => $attachment->getClientOriginalName(),
                    ]);
                }
            }
        }

        if ($request->status !== $oldStatus && $request->status === 'approved') {
            // Send notification to the passport holder about visa approval
            $passportHolder = $data->passportHolder;
            $country = $data->country;
            $visaCategory = $data->visaCategory;

            $message = "Dear {$passportHolder->name}, your visa application for {$country->name} under the category of {$visaCategory->name} has been approved.";

            // Send SMS
            sendSms($passportHolder->phone, $message);

            // Send Email
            sendBrevoMail($passportHolder->email, $passportHolder->name, 'Visa Application Approved', "<p>{$message}</p>");
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data,
            ]);
        }

        return redirect()->route('role.visa.index', ['role' => $role])
            ->with('success', 'Visa processing updated successfully.');
    }

    /**
     * Delete a specific attachment.
     */
    public function updateStatus(Request $request, string $_role, VisaProcess $visa)
    {
        $request->validate([
            'status' => 'required|in:pending,received,in_embassy,approved,delivered,rejected',
        ]);
        $visa->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }

    public function deleteAttachment(Request $request, $role, $visaId, $attachmentId)
    {
        $attachment = VisaAttachment::where('id', $attachmentId)
            ->where('visa_process_id', $visaId)
            ->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found.',
            ]);
        }

        // Delete the file from storage
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully.',
        ]);
    }

    /**
     * Get full details for a visa process (for the details modal).
     */
    public function details($role, VisaProcess $visa)
    {
        $visa->load(['passportHolder', 'country', 'visaCategory', 'assignedOfficer', 'visaAttachments', 'processDocuments']);

        return response()->json([
            'success' => true,
            'data' => $visa,
        ]);
    }

    /**
     * Get attachments for a visa process.
     */
    public function getAttachments($role, $visaId)
    {
        $attachments = VisaAttachment::where('visa_process_id', $visaId)->get();

        return response()->json([
            'success' => true,
            'attachments' => $attachments,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $role, string $id)
    {
        $data = VisaProcess::find($request->item_id ?? $id);
        if (!$data) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!',
                ]);
            }

            return redirect()->route('role.visa.index', ['role' => $role])
                ->with('error', 'Data Info Not Found!');
        }

        PaymentSchedule::where('schedulable_type', VisaProcess::class)
            ->where('schedulable_id', $data->id)
            ->delete();
        $this->deletePurchaseJournal('visa_process', $data->id);
        $this->reversePurchaseCostPayment('visa_process', $data->id);

        $data->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data deleted successfully.',
            ]);
        }

        return redirect()->route('role.visa.index', ['role' => $role])
            ->with('success', 'Visa processing deleted successfully.');
    }

    /**
     * Settle a visa process's payable to its vendor — Debit AP (2110) /
     * Credit Bank, and mark the linked PaymentSchedule row paid.
     */
    public function payVendor(Request $request, $role, VisaProcess $visa)
    {
        $validated = $request->validate([
            'bank_id'        => 'required|exists:banks,id',
            'payment_date'   => 'required|date',
            'reference_no'   => 'nullable|string|max:255',
            'payment_amount' => 'required|numeric|min:0.01',
            'schedule_id'    => 'nullable|exists:payment_schedules,id',
        ]);

        if ($validated['payment_amount'] > (float) $visa->due_amount) {
            $message = 'Payment amount cannot exceed due amount!';
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $message])
                : redirect()->back()->with('error', $message);
        }

        DB::transaction(function () use ($validated, $visa) {
            $bank = \App\Models\Bank::find($validated['bank_id']);
            if (!$bank || !$bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }

            $newCostPaid = min((float) $visa->cost_paid_amount + $validated['payment_amount'], (float) $visa->costing_price);
            $newDue      = max(0, (float) $visa->costing_price - $newCostPaid);

            $this->reconcileCostPayment('visa_process', $visa->id, $bank->id, null, $newCostPaid, $visa->vendor_id, $validated['payment_date']);

            $this->postPurchaseJournalPayment(
                'visa_process',
                $visa->id,
                Auth::user()->company_id ?? 2,
                $validated['payment_date'],
                $validated['reference_no'] ?? $visa->application_id,
                'Visa processing payment made — ' . $visa->application_id,
                (float) $validated['payment_amount'],
                $bank->account_id
            );

            $visa->forceFill(['due_amount' => $newDue, 'cost_paid_amount' => $newCostPaid])->saveQuietly();

            $scheduleQuery = PaymentSchedule::where('schedulable_type', VisaProcess::class)
                ->where('schedulable_id', $visa->id)
                ->where('type', 'pay');

            if (!empty($validated['schedule_id'])) {
                $scheduleQuery->where('id', $validated['schedule_id']);
            }

            if ($newDue <= 0) {
                $scheduleQuery->update(['status' => 'paid', 'paid_date' => $validated['payment_date']]);
            } else {
                $scheduleQuery->update(['paid_amount' => (float) $validated['payment_amount'], 'status' => 'pending']);
            }
        });

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Vendor payment recorded successfully.']);
        }

        return redirect()->route('role.visa.index', ['role' => $role])
            ->with('success', 'Vendor payment recorded successfully.');
    }

    private function syncPaymentSchedules(VisaProcess $visa): void
    {
        $companyId = Auth::user()->company_id ?? null;

        $visa->loadMissing('vendor', 'country');

        $costingPrice = (float) $visa->costing_price;
        $costPaid     = min((float) $visa->cost_paid_amount, $costingPrice);
        $due          = max(0, $costingPrice - $costPaid);

        // Keep a real due_amount column in sync so the generic Board's
        // markPaid() has something real to decrement.
        $visa->forceFill(['due_amount' => $due])->saveQuietly();

        $paymentAccountId = null;
        if ($visa->cost_bank_id) {
            $paymentAccountId = Bank::find($visa->cost_bank_id)?->account_id;
        } elseif ($visa->portal_id) {
            $paymentAccountId = Portal::find($visa->portal_id)?->account_id;
        }

        // Bank/portal balance movement for the paid portion — idempotent,
        // safe to re-run on every save (reverses the previous entry first).
        $this->reconcileCostPayment(
            'visa_process',
            $visa->id,
            $visa->cost_bank_id,
            $visa->portal_id,
            $costPaid,
            $visa->vendor_id,
            $visa->purchase_date ?? now()
        );

        // Liability recognition on the vendor's party statement — the full
        // cost owed, separate from the paid-portion credit row above, so the
        // statement shows what's owed even before anything is paid. Mirrors
        // the debit-row-on-invoice-creation pattern already used on the
        // sale/AR side (PostsPartyLedger::reconcilePartyLedgerRow).
        if ($visa->vendor_id && $costingPrice > 0) {
            $this->reconcilePartyLedgerRow(
                $visa->vendor_id, 'visa_process', $visa->id, true, $costingPrice,
                [
                    'account_id'   => null,
                    'payment_date' => $visa->purchase_date ?? now(),
                    'reference_no' => $visa->application_id,
                    'remarks'      => 'Visa processing cost — ' . $visa->application_id,
                ]
            );
        }

        // Remove any existing schedule entries for this visa
        PaymentSchedule::where('schedulable_type', VisaProcess::class)
            ->where('schedulable_id', $visa->id)
            ->delete();

        if ($costingPrice > 0) {
            $this->updatePurchaseJournal(
                'visa_process',
                $visa->id,
                Auth::user()->company_id ?? 2,
                $visa->purchase_date ?? $visa->payable_date ?? now(),
                $visa->application_id,
                'Visa processing cost — ' . $visa->application_id,
                $costingPrice,
                $costPaid,
                $due,
                $paymentAccountId,
                config('accounts.visa_processing_cost')
            );

            // Payable reminder schedule — only for vendor-funded costs with a
            // remaining balance. A portal-funded cost is already fully paid
            // out of a prepaid balance, so there's nothing to be reminded of.
            if ($due > 0 && !$visa->portal_id) {
                PaymentSchedule::create([
                    'company_id'       => $companyId,
                    'schedulable_type' => VisaProcess::class,
                    'schedulable_id'   => $visa->id,
                    'type'             => 'pay',
                    'party_type'       => 'vendor',
                    'party_id'         => $visa->vendor_id,
                    'party_name'       => $visa->vendor?->name ?? 'Vendor (unassigned)',
                    'source_label'     => $visa->application_id,
                    'amount'           => $due,
                    'paid_amount'      => 0,
                    'scheduled_date'   => $visa->payable_date?->toDateString() ?? now()->toDateString(),
                    'status'           => 'pending',
                    'created_by'       => Auth::id(),
                ]);
            }
        } else {
            $this->deletePurchaseJournal('visa_process', $visa->id);
        }
    }

    private function generateApplicationId(): string
    {
        $last = VisaProcess::withTrashed()
            ->whereNotNull('application_id')
            ->orderByDesc('id')
            ->value('application_id');

        if ($last && preg_match('/VISA-(\d+)/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = VisaProcess::withTrashed()->count() + 1;
        }

        return 'VISA-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    private function storePublicFile(UploadedFile $file, string $directory, ?string $oldPath = null): string
    {
        $fileName = uniqid() . '_' . time() . '.' . strtolower($file->getClientOriginalExtension());

        if (! file_exists(public_path($directory))) {
            mkdir(public_path($directory), 0777, true);
        }

        if (! empty($oldPath) && file_exists(public_path($oldPath))) {
            unlink(public_path($oldPath));
        }

        $file->move(public_path($directory), $fileName);

        return trim($directory, '/') . '/' . $fileName;
    }
}
