<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Bank;
use App\Models\ContractFile;
use App\Models\ContractFileCategory;
use App\Models\Country;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use App\Models\PaymentSchedule;
use App\Models\Portal;
use App\Models\User;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContractFileController implements HasMiddleware
{
    use PostsPartyLedger, PostsPurchaseJournal;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract file|view all contract file', only: ['index']),
            new Middleware('permission:create contract file', only: ['store']),
            new Middleware('permission:edit contract file', only: ['update']),
            new Middleware('permission:delete contract file', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = ContractFile::with(['passportHolder', 'country', 'category', 'vendor'])->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'like', "%{$search}%")
                    ->orWhereHas('passportHolder', fn ($holder) => $holder->where('name', 'like', "%{$search}%")
                        ->orWhere('passport_no', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->filled('contract_file_category_id')) {
            $query->where('contract_file_category_id', $request->contract_file_category_id);
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->paginate(25)->withQueryString();
        $allFiles = ContractFile::with(['passportHolder', 'vendor'])->get();

        $stats = [
            'total_files' => ContractFile::count(),
            'submitted_to_vendor' => ContractFile::where('status', 'submitted_to_vendor')->count(),
            'approved_mtd' => ContractFile::where('status', 'approved')
                ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'delivered' => ContractFile::where('status', 'delivered')->count(),
        ];

        $clientSummary = $allFiles
            ->groupBy('applicant_name')
            ->map(fn ($items, $name) => [
                'name' => $name,
                'total' => $items->count(),
                'in_process' => $items->whereIn('status', ['doc_collection', 'submitted_to_vendor', 'under_process'])->count(),
                'approved' => $items->where('status', 'approved')->count(),
            ])
            ->sortByDesc('total')
            ->take(4)
            ->values();

        $vendorSummary = $allFiles
            ->filter(fn ($file) => $file->vendor)
            ->groupBy('vendor_id')
            ->map(fn ($items) => [
                'name' => $items->first()->vendor?->name ?? 'Unassigned',
                'submitted' => $items->count(),
                'pending' => $items->whereIn('status', ['doc_collection', 'submitted_to_vendor', 'under_process'])->count(),
                'approved' => $items->where('status', 'approved')->count(),
            ])
            ->sortByDesc('submitted')
            ->take(4)
            ->values();

        $countries = Country::whereHas('contractFileCategories', function ($query) {
                $query->where('status', 'active')->whereNotNull('country_id');
            })
            ->orderBy('name')
            ->get(['id', 'name']);
        $passportHolders = PassportHolder::orderBy('name')->get(['id', 'name', 'passport_no', 'phone', 'nationality', 'date_of_birth']);
        $phCategories = PassportHolderCategory::orderBy('name')->get(['id', 'name']);
        $categories = ContractFileCategory::with('country')->where('status', 'active')->orderBy('name')->get();
        $vendors = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['vendor', 'agent']))
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
        $banks = Bank::where('status', 1)->get();
        $portals = Portal::where('status', 'active')->orderBy('name')->get();

        $categoriesJson = $categories->map(fn ($category) => [
            'id' => $category->id,
            'name' => $category->name,
            'country_id' => $category->country_id,
            'country_name' => $category->country?->name,
            'visa_rate' => (float) $category->visa_rate,
            'documents_list' => $category->documents_list,
        ]);

        $passportHoldersJson = $passportHolders->map(fn ($holder) => [
            'id' => $holder->id,
            'name' => $holder->name,
            'passport_no' => $holder->passport_no,
            'phone' => $holder->phone,
            'nationality' => $holder->nationality,
            'date_of_birth' => $holder->date_of_birth,
        ]);

        $filesJson = $datas->getCollection()->map(fn ($file) => [
            'id' => $file->id,
            'file_number' => $file->file_number,
            'passport_holder_id' => $file->passport_holder_id,
            'applicant_name' => $file->applicant_name,
            'phone' => $file->phone,
            'passport_no' => $file->passport_no,
            'date_of_birth' => optional($file->date_of_birth)->toDateString(),
            'country_id' => $file->country_id,
            'contract_file_category_id' => $file->contract_file_category_id,
            'vendor_id' => $file->vendor_id,
            'portal_id' => $file->portal_id,
            'cost_bank_id' => $file->cost_bank_id,
            'cost_paid_amount' => (float) $file->cost_paid_amount,
            'purchase_date' => optional($file->purchase_date)->toDateString(),
            'visa_rate' => (float) $file->visa_rate,
            'payable_date' => optional($file->payable_date)->toDateString(),
            'submit_date' => optional($file->submit_date)->toDateString(),
            'expected_out_date' => optional($file->expected_out_date)->toDateString(),
            'status' => $file->status,
            'status_label' => $file->status_label,
            'required_documents' => $file->required_documents ?? [],
            'notes' => $file->notes,
            'country' => $file->country ? ['id' => $file->country->id, 'name' => $file->country->name] : null,
            'category' => $file->category ? ['id' => $file->category->id, 'name' => $file->category->name] : null,
            'vendor' => $file->vendor ? ['id' => $file->vendor->id, 'name' => $file->vendor->name, 'phone' => $file->vendor->phone] : null,
            'passport_holder' => $file->passportHolder ? [
                'id' => $file->passportHolder->id,
                'name' => $file->passportHolder->name,
                'passport_no' => $file->passportHolder->passport_no,
                'phone' => $file->passportHolder->phone,
                'nationality' => $file->passportHolder->nationality,
                'date_of_birth' => $file->passportHolder->date_of_birth,
            ] : null,
        ]);

        $nextFileNumber = ContractFile::nextFileNumber();
        $statuses = ContractFile::STATUSES;

        return view('contract-files.index', compact(
            'datas',
            'stats',
            'clientSummary',
            'vendorSummary',
            'countries',
            'passportHolders',
            'passportHoldersJson',
            'phCategories',
            'categories',
            'vendors',
            'banks',
            'portals',
            'categoriesJson',
            'filesJson',
            'nextFileNumber',
            'statuses'
        ));
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            DB::transaction(function () use ($request) {
                $file = ContractFile::create($this->payload($request) + [
                    'file_number' => $request->file_number ?: ContractFile::nextFileNumber(),
                    'created_by' => Auth::id(),
                ]);
                $this->syncPaymentSchedules($file);
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Contract file created successfully.']);
    }

    public function update(Request $request, $role, string $id)
    {
        $file = ContractFile::find($id);
        if (!$file) {
            return response()->json(['success' => false, 'message' => 'Data Info Not Found!']);
        }

        $validator = $this->validator($request, $file->id);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            DB::transaction(function () use ($file, $request) {
                $file->update($this->payload($request) + [
                    'file_number' => $request->file_number,
                ]);
                $this->syncPaymentSchedules($file);
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Contract file updated successfully.']);
    }

    public function destroy(Request $request, $role, string $id)
    {
        try {
            $file = ContractFile::find($request->item_id ?? $id);
            if (!$file) {
                return response()->json(['success' => false, 'message' => 'Data Info Not Found!']);
            }

            DB::transaction(function () use ($file) {
                PaymentSchedule::where('schedulable_type', ContractFile::class)
                    ->where('schedulable_id', $file->id)
                    ->delete();
                $this->deletePurchaseJournal('contract_file', $file->id);
                $this->reversePurchaseCostPayment('contract_file', $file->id);
                $file->delete();
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Contract file deleted successfully.']);
    }

    /**
     * Settle a contract file's payable to its vendor — Debit AP (2110) /
     * Credit Bank, and mark the linked PaymentSchedule row paid.
     */
    public function payVendor(Request $request, $role, ContractFile $contractFile)
    {
        $validated = $request->validate([
            'bank_id'        => 'required|exists:banks,id',
            'payment_date'   => 'required|date',
            'reference_no'   => 'nullable|string|max:255',
            'payment_amount' => 'required|numeric|min:0.01',
            'schedule_id'    => 'nullable|exists:payment_schedules,id',
        ]);

        if ($validated['payment_amount'] > (float) $contractFile->due_amount) {
            $message = 'Payment amount cannot exceed due amount!';
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $message])
                : redirect()->back()->with('error', $message);
        }

        DB::transaction(function () use ($validated, $contractFile) {
            $bank = Bank::find($validated['bank_id']);
            if (!$bank || !$bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }

            $newCostPaid = min((float) $contractFile->cost_paid_amount + $validated['payment_amount'], (float) $contractFile->visa_rate);
            $newDue      = max(0, (float) $contractFile->visa_rate - $newCostPaid);

            $this->reconcileCostPayment('contract_file', $contractFile->id, $bank->id, null, $newCostPaid, $contractFile->vendor_id, $validated['payment_date']);

            $this->postPurchaseJournalPayment(
                'contract_file',
                $contractFile->id,
                Auth::user()->company_id ?? 2,
                $validated['payment_date'],
                $validated['reference_no'] ?? $contractFile->file_number,
                'Contract file vendor payment made — ' . $contractFile->file_number,
                (float) $validated['payment_amount'],
                $bank->account_id
            );

            $contractFile->forceFill(['due_amount' => $newDue, 'cost_paid_amount' => $newCostPaid])->saveQuietly();

            $scheduleQuery = PaymentSchedule::where('schedulable_type', ContractFile::class)
                ->where('schedulable_id', $contractFile->id)
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

        return redirect()->back()->with('success', 'Vendor payment recorded successfully.');
    }

    private function validator(Request $request, ?int $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'file_number' => ['nullable', 'string', 'max:50', Rule::unique('contract_files', 'file_number')->ignore($ignoreId)],
            'passport_holder_id' => 'required|exists:passport_holders,id',
            'country_id' => 'required|exists:countries,id',
            'contract_file_category_id' => 'required|exists:contract_file_categories,id',
            'vendor_id' => 'required|exists:users,id',
            'portal_id' => 'nullable|exists:portals,id',
            'cost_bank_id' => 'nullable|exists:banks,id',
            'cost_paid_amount' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'visa_rate' => 'required|numeric|min:0',
            'payable_date' => 'nullable|date',
            'submit_date' => 'required|date',
            'expected_out_date' => 'nullable|date',
            'status' => 'required|in:' . implode(',', array_keys(ContractFile::STATUSES)),
            'document_names' => 'nullable|array',
            'document_names.*' => 'nullable|string|max:255',
            'received_documents' => 'nullable|array',
            'received_documents.*' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
    }

    private function payload(Request $request): array
    {
        $holder = PassportHolder::findOrFail($request->passport_holder_id);

        return [
            'passport_holder_id' => $holder->id,
            'country_id' => $request->country_id,
            'contract_file_category_id' => $request->contract_file_category_id,
            'vendor_id' => $request->vendor_id,
            'portal_id' => $request->portal_id,
            'cost_bank_id' => $request->cost_bank_id,
            'cost_paid_amount' => $request->cost_paid_amount ?? 0,
            'purchase_date' => $request->purchase_date,
            'visa_rate' => $request->visa_rate ?? 0,
            'payable_date' => $request->payable_date,
            'submit_date' => $request->submit_date,
            'expected_out_date' => $request->expected_out_date,
            'status' => $request->status,
            'required_documents' => $this->documents($request),
            'notes' => $request->notes,
        ];
    }

    private function documents(Request $request): array
    {
        $received = collect($request->input('received_documents', []))->map(fn ($doc) => trim($doc))->filter()->values();

        return collect($request->input('document_names', []))
            ->map(fn ($doc) => trim((string) $doc))
            ->filter()
            ->unique()
            ->map(fn ($doc) => [
                'name' => $doc,
                'checked' => $received->contains($doc),
            ])
            ->values()
            ->all();
    }

    private function syncPaymentSchedules(ContractFile $file): void
    {
        $companyId = Auth::user()->company_id ?? null;

        $file->loadMissing('vendor');

        $visaRate = (float) $file->visa_rate;
        $costPaid = min((float) $file->cost_paid_amount, $visaRate);
        $due      = max(0, $visaRate - $costPaid);

        $file->forceFill(['due_amount' => $due])->saveQuietly();

        $paymentAccountId = null;
        if ($file->cost_bank_id) {
            $paymentAccountId = Bank::find($file->cost_bank_id)?->account_id;
        } elseif ($file->portal_id) {
            $paymentAccountId = Portal::find($file->portal_id)?->account_id;
        }

        $this->reconcileCostPayment(
            'contract_file',
            $file->id,
            $file->cost_bank_id,
            $file->portal_id,
            $costPaid,
            $file->vendor_id,
            $file->purchase_date ?? now()
        );

        // Liability recognition on the vendor's party statement — see
        // VisaProcessingController::syncPaymentSchedules() for rationale.
        if ($file->vendor_id && $visaRate > 0) {
            $this->reconcilePartyLedgerRow(
                $file->vendor_id, 'contract_file', $file->id, true, $visaRate,
                [
                    'account_id'   => null,
                    'payment_date' => $file->purchase_date ?? now(),
                    'reference_no' => $file->file_number,
                    'remarks'      => 'Contract file cost — ' . $file->file_number,
                ]
            );
        }

        PaymentSchedule::where('schedulable_type', ContractFile::class)
            ->where('schedulable_id', $file->id)
            ->delete();

        if ($visaRate > 0) {
            $this->updatePurchaseJournal(
                'contract_file',
                $file->id,
                Auth::user()->company_id ?? 2,
                $file->purchase_date ?? $file->payable_date ?? now(),
                $file->file_number,
                'Contract file vendor cost — ' . $file->file_number,
                $visaRate,
                $costPaid,
                $due,
                $paymentAccountId,
                config('accounts.contract_file_cost')
            );

            if ($due > 0 && !$file->portal_id) {
                PaymentSchedule::create([
                    'company_id'       => $companyId,
                    'schedulable_type' => ContractFile::class,
                    'schedulable_id'   => $file->id,
                    'type'             => 'pay',
                    'party_type'       => 'vendor',
                    'party_id'         => $file->vendor_id,
                    'party_name'       => $file->vendor?->name ?? 'Vendor (unassigned)',
                    'source_label'     => $file->file_number,
                    'amount'           => $due,
                    'paid_amount'      => 0,
                    'scheduled_date'   => $file->payable_date?->toDateString() ?? now()->toDateString(),
                    'status'           => 'pending',
                    'created_by'       => Auth::id(),
                ]);
            }
        } else {
            $this->deletePurchaseJournal('contract_file', $file->id);
        }
    }
}
