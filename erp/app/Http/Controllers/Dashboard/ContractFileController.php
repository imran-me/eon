<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\SettlementSourceOverdrawnException;
use App\Models\Account;
use App\Models\Bank;
use App\Models\ContractFile;
use App\Models\ContractFileCategory;
use App\Models\ContractFileDocument;
use App\Models\ContractFileVisaType;
use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use App\Models\PaymentSchedule;
use App\Models\Portal;
use App\Models\User;
use App\Services\ContractFileReporting;
use App\Services\ContractFileSetup;
use App\Support\TravelCompany;
use App\Traits\ChangesContractFileMoney;
use App\Traits\CreatesContractFileBatch;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use App\Traits\PostsSaleJournal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractFileController implements HasMiddleware
{
    use ChangesContractFileMoney;
    use CreatesContractFileBatch;
    use PostsPartyLedger, PostsPurchaseJournal, PostsSaleJournal;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract file|view all contract file', only: ['index', 'show', 'document', 'dashboard', 'agentFiles', 'board', 'accounts']),
            new Middleware('permission:create contract file', only: ['store']),
            new Middleware('permission:edit contract file', only: ['update', 'updateStage', 'updateException', 'updateDocument', 'moneyAction']),
            new Middleware('permission:delete contract file', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date', 'to_date' => 'nullable|date|after_or_equal:from_date',
            'client_id' => 'nullable|integer|exists:users,id',
            'stage_filter' => ['nullable', Rule::in(['active', 'documents', 'processing', 'due', 'cancelled', 'rejected', 'delivered', 'returned'])],
        ]);
        $query = ContractFile::with(['passportHolder', 'country', 'category', 'vendor', 'client'])->orderByDesc('id');
        if (Schema::hasTable('contract_file_service_costs')) {
            $query->with('serviceCosts.vendor');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'like', "%{$search}%")
                    ->orWhereHas('passportHolder', fn ($holder) => $holder->where('name', 'like', "%{$search}%")
                        ->orWhere('passport_no', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
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

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->boolean('unassigned')) {
            $query->whereNull('client_id');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        switch ($request->input('stage_filter')) {
            case 'active':
                $query->whereNotIn('status', ['delivered', 'case_closed'])->where(fn ($q) => $q->whereNull('exception_flag')->orWhereNotIn('exception_flag', ['rejected', 'cancelled']));
                break;
            case 'processing': $query->whereIn('status', ['submitted_to_vendor', 'biometrics_appointment', 'under_process', 'additional_documents']);
                break;
            case 'cancelled': case 'rejected': $query->where('exception_flag', $request->input('stage_filter'));
                break;
            case 'delivered': $query->whereIn('status', ['delivered', 'case_closed']);
                break;
            case 'returned': $query->whereIn('exception_flag', ['returned', 'resubmitted']);
                break;
            case 'documents': case 'due':
                // Match the card's calculated checklist / net balance, not a raw status.
                $candidates = (clone $query)->get();
                if (Schema::hasTable('contract_file_changes')) {
                    $candidates->loadMissing('moneyChanges');
                }
                $matching = $candidates->filter(fn ($file) => $request->input('stage_filter') === 'documents'
                    ? $file->documents_received < $file->documents_total : $file->client_due > 0);
                $query->whereKey($matching->modelKeys());
                break;
        }

        if ($request->filled('from_date')) {
            $query->whereDate('submit_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('submit_date', '<=', $request->to_date);
        }

        $groupClient = $request->attributes->get('file_group_client');
        $groupFiles = ($groupClient || $request->boolean('unassigned'))
            ? ContractFile::with(['vendor', 'client', 'country', 'category'])
                ->when($groupClient, fn ($q) => $q->where('client_id', $groupClient->id), fn ($q) => $q->whereNull('client_id'))->get()
            : collect();

        $boardFiles = $request->routeIs('role.contract-files.board') ? (clone $query)->reorder()->orderBy('id')->get() : collect();
        // The File Dashboard draws one card and one row per FILE (owner, 2026-10-25), each naming its
        // applicant, agent and vendor — loaded here in bulk rather than once per row.
        $dashboardFiles = $request->routeIs('role.contract-files.dashboard') ? (clone $query)->with(['country', 'category', 'client', 'vendor', 'passportHolder'])->get() : collect();
        $datas = $query->paginate(25)->withQueryString();
        $fileAccounts = $request->routeIs('role.contract-files.accounts')
            ? ContractFileReporting::workspace((clone $query)->get()) : null;
        $allFiles = $request->routeIs('role.contract-files.dashboard') ? $dashboardFiles : ContractFile::with(['passportHolder', 'vendor', 'client', 'country', 'category'])->get();
        // Setup's visa type, in bulk — and only once its table exists (an eager load would query it).
        if (ContractFileSetup::installed()) {
            foreach ([$datas->getCollection(), $groupFiles, $allFiles, $dashboardFiles, $boardFiles] as $collection) {
                if ($collection instanceof Collection) {
                    $collection->loadMissing('visaType');
                }
            }
        }
        if (Schema::hasTable('contract_file_changes')) {
            foreach ([$datas->getCollection(), $groupFiles, $allFiles, $dashboardFiles, $boardFiles] as $collection) {
                if ($collection instanceof Collection) {
                    $collection->loadMissing(['moneyChanges', 'serviceCosts.vendor', 'serviceCosts.refundChanges']);
                }
            }
        }

        $stats = [
            'total_files' => ContractFile::count(),
            'submitted_to_vendor' => ContractFile::whereIn('status', ['submitted_to_vendor', 'biometrics_appointment', 'under_process', 'additional_documents'])->count(),
            'approved_mtd' => ContractFile::where('status', 'approved')
                ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'delivered' => ContractFile::where('status', 'delivered')->count(),
        ];

        $clientSummary = $allFiles
            ->groupBy(fn ($file) => $file->client_id ?: 'direct-unassigned')
            ->map(fn ($items) => [
                'client_id' => $items->first()->client_id,
                'files' => $items,
                'cost' => (float) $items->sum('total_file_cost'),
                'vendor_due' => (float) $items->sum('total_vendor_due'),
                'name' => $items->first()->client?->name ?? 'Direct / Unassigned',
                'total' => $items->count(),
                'charge' => (float) $items->sum('net_client_charge'),
                'due' => (float) $items->sum('client_due_amount'),
                'in_process' => $items->whereIn('status', ['file_received', 'information_entered', 'documents_pending', 'documents_verified', 'application_prepared', 'submitted_to_vendor', 'biometrics_appointment', 'under_process', 'additional_documents'])->count(),
                'approved' => $items->where('status', 'approved')->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $vendorSummary = $allFiles
            ->filter(fn ($file) => $file->vendor)
            ->groupBy('vendor_id')
            ->map(fn ($items) => [
                'name' => $items->first()->vendor?->name ?? 'Unassigned',
                'submitted' => $items->count(),
                'pending' => $items->whereIn('status', ['file_received', 'information_entered', 'documents_pending', 'documents_verified', 'application_prepared', 'submitted_to_vendor', 'biometrics_appointment', 'under_process', 'additional_documents'])->count(),
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
        $clients = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['agent', 'customer']))
            ->with('roles:id,name')->orderBy('name')->get(['id', 'name', 'phone']);
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
            'contract_file_visa_type_id' => ContractFileSetup::installed() ? $file->contract_file_visa_type_id : null,
            'vendor_id' => $file->vendor_id,
            'client_id' => $file->client_id,
            'client_type' => $file->client_type,
            'client_price' => (float) $file->client_price,
            'client_paid_amount' => (float) $file->client_paid_amount,
            'edit_charge_adjustment' => $file->net_client_charge - (float) $file->client_price,
            'edit_processing_refund' => $file->processing_refund,
            'edit_services' => $file->serviceCosts->map(fn ($service) => ['title' => $service->title, 'provider' => $service->vendor?->name, 'cost' => $service->voided_at ? 0 : $service->net_amount, 'paid' => $service->paid_amount, 'due' => $service->due_amount])->values(),
            'client_due_amount' => (float) $file->client_due_amount,
            'portal_id' => $file->portal_id,
            'cost_bank_id' => $file->cost_bank_id,
            'cost_paid_amount' => (float) $file->cost_paid_amount,
            'purchase_date' => optional($file->purchase_date)->toDateString(),
            'visa_rate' => (float) $file->visa_rate,
            'payable_date' => optional($file->payable_date)->toDateString(),
            'submit_date' => optional($file->submit_date)->toDateString(),
            'expected_out_date' => optional($file->expected_out_date)->toDateString(),
            'status' => $file->status,
            'exception_flag' => $file->exception_flag,
            'status_label' => $file->status_label,
            'required_documents' => $file->required_documents ?? [],
            'notes' => $file->notes,
            'country' => $file->country ? ['id' => $file->country->id, 'name' => $file->country->name] : null,
            'category' => $file->category ? ['id' => $file->category->id, 'name' => $file->category->name] : null,
            'vendor' => $file->vendor ? ['id' => $file->vendor->id, 'name' => $file->vendor->name, 'phone' => $file->vendor->phone] : null,
            'client' => $file->client ? ['id' => $file->client->id, 'name' => $file->client->name, 'phone' => $file->client->phone] : null,
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
        $builderReady = Schema::hasTable('contract_file_service_costs') && Schema::hasTable('contract_file_documents');
        $fileTrainers = User::where(function ($q) {
            $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['trainer', 'consultant']));
            if (Schema::hasColumn('users', 'service_categories')) {
                $q->orWhere(fn ($s) => $s->whereJsonContains('service_categories', 'instructor')->whereHas('roles', fn ($r) => $r->whereIn('name', ['agent', 'vendor'])));
            }
        })->get();
        $fileDocuments = $builderReady ? ContractFileDocument::select('id', 'contract_file_id', 'passport_holder_id', 'original_name', 'title')->get() : collect();
        // Setup (2026-10-25): the visa types to pick on a file, and the endpoint that fills documents and
        // figures. Both empty until Setup's migration ran AND its routes are registered — then the file
        // entry falls back to the job category alone, as before.
        $setupMatchUrl = ContractFileSetup::installed() && Route::has('role.contract-file-setup.match')
            ? route('role.contract-file-setup.match', ['role' => $request->route('role')]) : null;
        // All of them, so editing a file never blanks an inactive type it carries; new files list the active ones.
        $fileVisaTypes = $setupMatchUrl ? ContractFileVisaType::orderBy('name')->get(['id', 'name', 'status']) : collect();

        return view('contract-files.index', compact(
            'datas',
            'boardFiles',
            'dashboardFiles',
            'allFiles',
            'fileAccounts',
            'builderReady', 'fileTrainers', 'fileDocuments', 'setupMatchUrl', 'fileVisaTypes',
            'stats',
            'clientSummary',
            'groupClient',
            'groupFiles',
            'vendorSummary',
            'countries',
            'passportHolders',
            'passportHoldersJson',
            'phCategories',
            'categories',
            'vendors',
            'clients',
            'banks',
            'portals',
            'categoriesJson',
            'filesJson',
            'nextFileNumber',
            'statuses'
        ));
    }

    public function dashboard(Request $request)
    {
        return $this->index($request);
    }

    public function board(Request $request)
    {
        return $this->index($request);
    }

    public function accounts(Request $request)
    {
        return $this->index($request);
    }

    public function agentFiles(Request $request, string $role, User $client)
    {
        $request->merge(['client_id' => $client->id]);
        $request->attributes->set('file_group_client', $client);

        return $this->index($request);
    }

    public function store(Request $request)
    {
        if ($request->has('applicants')) {
            try {
                return $this->storeBatch($request);
            } catch (SettlementSourceOverdrawnException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }
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
                $this->createClientPosting($file);
                $this->recordEvent($file, 'stage', $file->status, $file->status_label, 'File created');
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Contract file created successfully.']);
    }

    public function document(Request $request, string $role, ContractFileDocument $document)
    {
        abort_unless($document->file, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function updateDocument(Request $request, string $role, ContractFileDocument $document)
    {
        abort_unless($document->file, 404);
        $data = $request->validate(['status' => 'required|in:received,verified,returned', 'note' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($document, $data) {
            $document->update(['status' => $data['status'], 'verified_by' => $data['status'] === 'verified' ? Auth::id() : null, 'verified_at' => $data['status'] === 'verified' ? now() : null, 'notes' => $data['note'] ?? $document->notes]);
            $this->recordEvent($document->file, 'document', $data['status'], 'Document '.$data['status'].': '.$document->title, $data['note'] ?? null);
        });

        return response()->json(['success' => true]);
    }

    public function update(Request $request, $role, string $id)
    {
        $file = ContractFile::find($id);
        if (! $file) {
            return response()->json(['success' => false, 'message' => 'Data Info Not Found!']);
        }

        $validator = $this->validator($request, $file->id);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $additionalApplicants = $request->input('additional_applicants', []);
        if ($additionalApplicants && ! Auth::user()->can('create contract file')) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to create additional contract files.'], 403);
        }
        if (collect($additionalApplicants)->contains(fn ($row) => (int) ($row['passport_holder_id'] ?? 0) === (int) $request->passport_holder_id)) {
            return response()->json(['success' => false, 'message' => 'The current passport holder cannot be added twice.']);
        }
        foreach ($additionalApplicants as $row) {
            $category = ContractFileCategory::find($row['contract_file_category_id'] ?? null);
            if (! $category || ($category->country_id && (int) $category->country_id !== (int) ($row['country_id'] ?? 0))) {
                return response()->json(['success' => false, 'message' => 'An additional applicant category does not belong to the selected country.']);
            }
            if ((float) ($row['cost_paid_amount'] ?? 0) > (float) ($row['visa_rate'] ?? 0) || ((float) ($row['cost_paid_amount'] ?? 0) > 0 && empty($row['cost_bank_id']))) {
                return response()->json(['success' => false, 'message' => 'Additional applicant processing payment must not exceed cost and requires a bank.']);
            }
            foreach (['training', 'others'] as $serviceKey) {
                $service = $row[$serviceKey] ?? [];
                if (empty($service['enabled'])) {
                    continue;
                }
                if (empty($service['vendor_id']) || ($serviceKey === 'others' && empty(trim($service['title'] ?? '')))) {
                    return response()->json(['success' => false, 'message' => 'Each enabled additional service requires a provider and title where applicable.']);
                }
                if ($serviceKey === 'training') {
                    $provider = User::find($service['vendor_id']);
                    $eligible = $provider && ($provider->hasAnyRole(['trainer', 'consultant']) || (in_array('instructor', (array) $provider->service_categories, true) && $provider->hasAnyRole(['agent', 'vendor'])));
                    if (! $eligible) {
                        return response()->json(['success' => false, 'message' => 'Select an eligible trainer for each additional applicant.']);
                    }
                }
                if ((float) ($service['paid_amount'] ?? 0) > (float) ($service['amount'] ?? 0) || ((float) ($service['paid_amount'] ?? 0) > 0 && (empty($service['bank_id']) || empty($service['payment_date'])))) {
                    return response()->json(['success' => false, 'message' => 'Additional service payment must not exceed cost and requires a bank and payment date.']);
                }
            }
            foreach ($row['linked_documents'] ?? [] as $documentId) {
                $document = ContractFileDocument::find($documentId);
                if (! $document || (int) $document->passport_holder_id !== (int) ($row['passport_holder_id'] ?? 0)) {
                    return response()->json(['success' => false, 'message' => 'A linked document belongs to a different passport holder.']);
                }
            }
        }
        foreach ($request->input('primary_linked_documents', []) as $documentId) {
            $document = ContractFileDocument::find($documentId);
            if (! $document || (int) $document->passport_holder_id !== (int) $request->passport_holder_id) {
                return response()->json(['success' => false, 'message' => 'A linked document belongs to a different passport holder.']);
            }
        }

        try {
            DB::transaction(function () use ($file, $request, $additionalApplicants) {
                $file = ContractFile::whereKey($file->id)->lockForUpdate()->firstOrFail();
                $hasChanges = $file->file_changes->isNotEmpty();
                if ($hasChanges) {
                    foreach (['client_id', 'client_type', 'client_price', 'vendor_id', 'visa_rate', 'cost_paid_amount', 'cost_bank_id', 'portal_id', 'purchase_date', 'payable_date'] as $key) {
                        if ((string) ($this->payload($request)[$key] ?? '') !== (string) ($file->getRawOriginal($key) ?? '')) {
                            // Decimal columns may serialize as "5000.00" rather than "5000".
                            if (in_array($key, ['client_id', 'client_price', 'vendor_id', 'visa_rate', 'cost_paid_amount', 'cost_bank_id', 'portal_id'], true) && (float) $request->input($key) === (float) $file->getRawOriginal($key)) {
                                continue;
                            }
                            throw ValidationException::withMessages([$key => 'This file has financial change history. Use Refund / Re-process / Others instead of rewriting its original financial fields.']);
                        }
                    }
                }
                $before = $file->only(['client_id', 'client_price', 'client_paid_amount', 'vendor_id', 'visa_rate', 'status', 'exception_flag']);
                $file->update($this->payload($request) + [
                    'file_number' => $request->file_number,
                ]);
                if (! $hasChanges) {
                    $this->syncPaymentSchedules($file, $before);
                    $this->adjustClientPosting($file, $before);
                }
                if ($before['status'] !== $file->status) {
                    $this->recordEvent($file, 'stage', $file->status, $file->status_label);
                }
                foreach ($request->file('primary_documents', []) as $upload) {
                    $path = $upload->store('contract-files/'.$file->id, 'local');
                    ContractFileDocument::create([
                        'contract_file_id' => $file->id, 'passport_holder_id' => $file->passport_holder_id,
                        'document_type' => 'supporting', 'title' => mb_substr($upload->getClientOriginalName(), 0, 160),
                        'disk' => 'local', 'path' => $path, 'original_name' => $upload->getClientOriginalName(),
                        'mime_type' => $upload->getMimeType(), 'size' => $upload->getSize(),
                        'sha256' => hash_file('sha256', $upload->getRealPath()), 'status' => 'received', 'uploaded_by' => Auth::id(),
                    ]);
                }
                foreach ($request->input('primary_linked_documents', []) as $documentId) {
                    $source = ContractFileDocument::findOrFail($documentId);
                    if ((int) $source->contract_file_id === (int) $file->id) {
                        continue;
                    }
                    ContractFileDocument::create($source->only([
                        'passport_holder_id', 'document_type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256',
                    ]) + ['contract_file_id' => $file->id, 'linked_document_id' => $source->id, 'status' => 'received', 'uploaded_by' => Auth::id()]);
                }
                foreach ($additionalApplicants as $additionalIndex => $row) {
                    $received = collect($row['received_documents'] ?? [])->map(fn ($name) => trim((string) $name))->filter();
                    $documents = collect($row['document_names'] ?? [])->map(fn ($name) => trim((string) $name))->filter()->unique()->map(fn ($name) => [
                        'name' => $name,
                        'checked' => $received->contains($name),
                    ])->values()->all();
                    $newFile = ContractFile::create(collect($row)->only([
                        'passport_holder_id', 'client_id', 'client_type', 'country_id', 'contract_file_category_id',
                        'vendor_id', 'client_price', 'visa_rate', 'cost_paid_amount', 'cost_bank_id', 'purchase_date',
                        'payable_date', 'submit_date', 'expected_out_date', 'notes',
                    ])->all() + [
                        'file_number' => ContractFile::nextFileNumber(), 'created_by' => Auth::id(),
                        'status' => 'file_received', 'required_documents' => $documents,
                    ]);
                    $this->syncPaymentSchedules($newFile);
                    $this->createClientPosting($newFile);
                    $this->recordEvent($newFile, 'stage', $newFile->status, $newFile->status_label, 'Created while editing '.$file->file_number);
                    if (! empty($row['training']['enabled'])) {
                        $this->createFileTraining($newFile, $row['training']);
                    }
                    if (! empty($row['others']['enabled'])) {
                        $this->createFileService($newFile, $row['others'], 'others', trim($row['others']['title']));
                    }
                    foreach ($request->file("additional_applicants.$additionalIndex.documents", []) as $upload) {
                        $path = $upload->store('contract-files/'.$newFile->id, 'local');
                        ContractFileDocument::create([
                            'contract_file_id' => $newFile->id, 'passport_holder_id' => $newFile->passport_holder_id,
                            'document_type' => 'supporting', 'title' => mb_substr($upload->getClientOriginalName(), 0, 160),
                            'disk' => 'local', 'path' => $path, 'original_name' => $upload->getClientOriginalName(),
                            'mime_type' => $upload->getMimeType(), 'size' => $upload->getSize(),
                            'sha256' => hash_file('sha256', $upload->getRealPath()), 'status' => 'received', 'uploaded_by' => Auth::id(),
                        ]);
                    }
                    foreach ($row['linked_documents'] ?? [] as $documentId) {
                        $source = ContractFileDocument::findOrFail($documentId);
                        ContractFileDocument::create($source->only([
                            'passport_holder_id', 'document_type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256',
                        ]) + ['contract_file_id' => $newFile->id, 'linked_document_id' => $source->id, 'status' => 'received', 'uploaded_by' => Auth::id()]);
                    }
                }
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Contract file updated successfully.']);
    }

    public function show(string $role, ContractFile $contractFile)
    {
        $contractFile->load(['passportHolder', 'country', 'category', 'vendor', 'client', 'events.creator']);
        if (request()->boolean('sheet')) {
            if (Schema::hasTable('contract_file_service_costs')) {
                $contractFile->load('serviceCosts.vendor');
            }
            if (Schema::hasTable('contract_file_documents')) {
                $contractFile->load('fileDocuments');
            }

            return view('contract-files.record-sheet', ['file' => $contractFile, 'role' => $role]);
        }
        $moneyVendors = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['vendor', 'agent', 'trainer', 'consultant']))->orderBy('name')->get(['id', 'name']);
        $moneyBanks = Bank::where('status', 1)->get();

        return view('contract-files.show', ['file' => $contractFile, 'stages' => ContractFile::STATUSES, 'moneyVendors' => $moneyVendors, 'moneyBanks' => $moneyBanks]);
    }

    public function updateStage(Request $request, string $role, ContractFile $contractFile)
    {
        $validated = $request->validate(['stage' => ['required', Rule::in(array_keys(ContractFile::STATUSES))], 'note' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($contractFile, $validated) {
            if ($contractFile->status === $validated['stage']) {
                return;
            }
            $contractFile->update(['status' => $validated['stage']]);
            $this->recordEvent($contractFile, 'stage', $validated['stage'], ContractFile::stageLabel($validated['stage']), $validated['note'] ?? null);
        });

        return response()->json(['success' => true, 'stage' => $contractFile->fresh()->status]);
    }

    public function updateException(Request $request, string $role, ContractFile $contractFile)
    {
        $validated = $request->validate([
            'exception_flag' => ['nullable', Rule::in(array_keys(ContractFile::EXCEPTION_FLAGS))],
            'note' => 'nullable|string|max:1000',
        ]);
        DB::transaction(function () use ($contractFile, $validated) {
            $old = $contractFile->exception_flag;
            $new = $validated['exception_flag'] ?? null;
            if ($old === $new) {
                return;
            }
            $contractFile->update(['exception_flag' => $new]);
            $this->applyExceptionAdjustments($contractFile, $old, $new);
            $label = $new ? ContractFile::EXCEPTION_FLAGS[$new] : 'Exception Cleared';
            $this->recordEvent($contractFile, 'exception', $new, $label, $validated['note'] ?? null);
        });

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, $role, string $id)
    {
        $validated = $request->validate([
            'deletion_reason' => ['required', 'string', 'min:5', 'max:500'],
            'confirmation' => ['required', 'in:CONFIRM'],
        ]);

        try {
            $file = ContractFile::find($request->item_id ?? $id);
            if (! $file) {
                return response()->json(['success' => false, 'message' => 'Data Info Not Found!']);
            }

            DB::transaction(function () use ($file, $validated) {
                if ($file->file_changes->isNotEmpty()) {
                    throw ValidationException::withMessages(['file' => 'Financial change history must remain visible. Use Refund or a cancellation flag instead of deleting this file.']);
                }
                $oldException = $file->exception_flag;
                $file->forceFill(['exception_flag' => 'cancelled'])->saveQuietly();
                $this->applyExceptionAdjustments($file, $oldException, 'cancelled');
                $this->recordEvent($file, 'exception', 'cancelled', ContractFile::EXCEPTION_FLAGS['cancelled'], 'Wrong entry deleted; financial entries retained and reversed. Reason: '.$validated['deletion_reason']);
                PaymentSchedule::where('schedulable_type', ContractFile::class)
                    ->where('schedulable_id', $file->id)
                    ->delete();
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
            'bank_id' => 'required|exists:banks,id',
            'payment_date' => 'required|date',
            'reference_no' => 'nullable|string|max:255',
            'payment_amount' => 'required|numeric|min:0.01',
            'schedule_id' => 'nullable|exists:payment_schedules,id',
        ]);

        if ($validated['payment_amount'] > (float) $contractFile->due_amount) {
            $message = 'Payment amount cannot exceed due amount!';

            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $message])
                : redirect()->back()->with('error', $message);
        }

        DB::transaction(function () use ($validated, $contractFile) {
            $contractFile = ContractFile::whereKey($contractFile->id)->lockForUpdate()->firstOrFail();
            if ($validated['payment_amount'] > (float) $contractFile->due_amount) {
                throw ValidationException::withMessages(['payment_amount' => 'Payment exceeds the current due.']);
            }
            $bank = Bank::whereKey($validated['bank_id'])->lockForUpdate()->first();
            if (! $bank || ! $bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }

            $newCostPaid = min((float) $contractFile->cost_paid_amount + $validated['payment_amount'], (float) $contractFile->visa_rate);
            $newDue = max(0, $contractFile->net_processing_cost - $newCostPaid);

            if ($contractFile->file_changes->isNotEmpty()) {
                User::whereKey($contractFile->vendor_id)->lockForUpdate()->firstOrFail();
                $this->postPartyLedgerRow($contractFile->vendor_id, ['type' => 'contract_file', 'invoice_id' => $contractFile->id, 'account_id' => $bank->id, 'payment_date' => $validated['payment_date'], 'reference_no' => $validated['reference_no'] ?? $contractFile->file_number, 'debit' => 0, 'credit' => $validated['payment_amount'], 'remarks' => 'Contract file vendor settlement — '.$contractFile->file_number]);
                $bank->decrement('balance', $validated['payment_amount']);
            } else {
                $this->reconcileCostPayment('contract_file', $contractFile->id, $bank->id, null, $newCostPaid, $contractFile->vendor_id, $validated['payment_date']);
            }

            $this->postPurchaseJournalPayment(
                'contract_file',
                $contractFile->id,
                TravelCompany::ID,
                $validated['payment_date'],
                $validated['reference_no'] ?? $contractFile->file_number,
                'Contract file vendor payment made — '.$contractFile->file_number,
                (float) $validated['payment_amount'],
                $bank->account_id,
                'vendor', $contractFile->vendor_id
            );

            $contractFile->forceFill(['due_amount' => $newDue, 'cost_paid_amount' => $newCostPaid])->saveQuietly();

            $scheduleQuery = PaymentSchedule::where('schedulable_type', ContractFile::class)
                ->where('schedulable_id', $contractFile->id)
                ->where('type', 'pay');

            if (! empty($validated['schedule_id'])) {
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
            'contract_file_visa_type_id' => ContractFileSetup::installed() ? ['nullable', 'integer', Rule::exists('contract_file_visa_types', 'id')->whereNull('deleted_at')] : 'nullable',
            'vendor_id' => 'required|exists:users,id',
            'client_id' => 'required|exists:users,id',
            'client_type' => ['required', Rule::in(['agent', 'direct'])],
            'client_price' => 'required|numeric|min:0',
            'portal_id' => 'nullable|exists:portals,id',
            'cost_bank_id' => 'nullable|exists:banks,id',
            'cost_paid_amount' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'visa_rate' => 'required|numeric|min:0',
            'payable_date' => 'nullable|date',
            'submit_date' => 'required|date',
            'expected_out_date' => 'nullable|date',
            'status' => 'required|in:'.implode(',', array_keys(ContractFile::STATUSES)),
            'exception_flag' => ['nullable', Rule::in(array_keys(ContractFile::EXCEPTION_FLAGS))],
            'document_names' => 'nullable|array',
            'document_names.*' => 'nullable|string|max:255',
            'received_documents' => 'nullable|array',
            'received_documents.*' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'primary_documents' => 'nullable|array|max:20',
            'primary_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'primary_linked_documents' => 'nullable|array|max:20',
            'primary_linked_documents.*' => 'integer|distinct|exists:contract_file_documents,id',
            'additional_applicants' => 'nullable|array|max:29',
            'additional_applicants.*.passport_holder_id' => 'required|integer|distinct|exists:passport_holders,id',
            'additional_applicants.*.client_id' => 'required|integer|exists:users,id',
            'additional_applicants.*.client_type' => ['required', Rule::in(['agent', 'direct'])],
            'additional_applicants.*.country_id' => 'required|integer|exists:countries,id',
            'additional_applicants.*.contract_file_category_id' => 'required|integer|exists:contract_file_categories,id',
            'additional_applicants.*.vendor_id' => 'required|integer|exists:users,id',
            'additional_applicants.*.client_price' => 'required|numeric|min:0|max:999999999',
            'additional_applicants.*.visa_rate' => 'required|numeric|min:0|max:999999999',
            'additional_applicants.*.cost_paid_amount' => 'nullable|numeric|min:0|max:999999999',
            'additional_applicants.*.cost_bank_id' => 'nullable|integer|exists:banks,id',
            'additional_applicants.*.purchase_date' => 'nullable|date',
            'additional_applicants.*.payable_date' => 'nullable|date',
            'additional_applicants.*.submit_date' => 'required|date',
            'additional_applicants.*.expected_out_date' => 'nullable|date',
            'additional_applicants.*.document_names' => 'nullable|array',
            'additional_applicants.*.document_names.*' => 'nullable|string|max:255',
            'additional_applicants.*.received_documents' => 'nullable|array',
            'additional_applicants.*.received_documents.*' => 'nullable|string|max:255',
            'additional_applicants.*.notes' => 'nullable|string|max:5000',
            'additional_applicants.*.documents' => 'nullable|array|max:20',
            'additional_applicants.*.documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'additional_applicants.*.linked_documents' => 'nullable|array|max:20',
            'additional_applicants.*.linked_documents.*' => 'integer|distinct|exists:contract_file_documents,id',
            'additional_applicants.*.training.enabled' => 'nullable|boolean',
            'additional_applicants.*.training.vendor_id' => 'nullable|integer|exists:users,id',
            'additional_applicants.*.training.amount' => 'nullable|numeric|min:0|max:999999999',
            'additional_applicants.*.training.paid_amount' => 'nullable|numeric|min:0|max:999999999',
            'additional_applicants.*.training.bank_id' => 'nullable|integer|exists:banks,id',
            'additional_applicants.*.training.incurred_on' => 'nullable|date',
            'additional_applicants.*.training.payment_date' => 'nullable|date',
            'additional_applicants.*.training.due_date' => 'nullable|date',
            'additional_applicants.*.others.enabled' => 'nullable|boolean',
            'additional_applicants.*.others.title' => 'nullable|string|max:160',
            'additional_applicants.*.others.vendor_id' => 'nullable|integer|exists:users,id',
            'additional_applicants.*.others.amount' => 'nullable|numeric|min:0|max:999999999',
            'additional_applicants.*.others.paid_amount' => 'nullable|numeric|min:0|max:999999999',
            'additional_applicants.*.others.bank_id' => 'nullable|integer|exists:banks,id',
            'additional_applicants.*.others.incurred_on' => 'nullable|date',
            'additional_applicants.*.others.payment_date' => 'nullable|date',
            'additional_applicants.*.others.due_date' => 'nullable|date',
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
            'client_id' => $request->client_id,
            'client_type' => $request->client_type,
            'client_price' => $request->client_price ?? 0,
            'portal_id' => $request->portal_id,
            'cost_bank_id' => $request->cost_bank_id,
            'cost_paid_amount' => $request->cost_paid_amount ?? 0,
            'purchase_date' => $request->purchase_date,
            'visa_rate' => $request->visa_rate ?? 0,
            'payable_date' => $request->payable_date,
            'submit_date' => $request->submit_date,
            'expected_out_date' => $request->expected_out_date,
            'status' => $request->status,
            'exception_flag' => $request->exception_flag,
            'required_documents' => $this->documents($request),
            'notes' => $request->notes,
        ] + (ContractFileSetup::installed() && $request->has('contract_file_visa_type_id')
            ? ['contract_file_visa_type_id' => $request->filled('contract_file_visa_type_id') ? (int) $request->contract_file_visa_type_id : null]
            : []);
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

    private function syncPaymentSchedules(ContractFile $file, ?array $before = null): void
    {
        $companyId = TravelCompany::ID;

        $file->loadMissing('vendor');

        $visaRate = (float) $file->visa_rate;
        $costPaid = min((float) $file->cost_paid_amount, $visaRate);
        $due = max(0, $visaRate - $costPaid);

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
        $vendorChanged = $before && ((int) $before['vendor_id'] !== (int) $file->vendor_id
            || (float) $before['visa_rate'] !== $visaRate);
        if ($vendorChanged) {
            if ($before['vendor_id'] && (float) $before['visa_rate'] > 0) {
                $this->postAdjustment((int) $before['vendor_id'], ContractFile::VENDOR_ADJUSTMENT_TYPE, $file, 0, (float) $before['visa_rate'], 'Previous vendor payable reversed');
            }
            if ($file->vendor_id && $visaRate > 0) {
                $this->postAdjustment((int) $file->vendor_id, ContractFile::VENDOR_ADJUSTMENT_TYPE, $file, $visaRate, 0, 'Updated vendor payable charged');
            }
        } elseif ($file->vendor_id && $visaRate > 0) {
            $this->reconcilePartyLedgerRow(
                $file->vendor_id, 'contract_file', $file->id, true, $visaRate,
                [
                    'account_id' => null,
                    'payment_date' => $file->purchase_date ?? now(),
                    'reference_no' => $file->file_number,
                    'remarks' => 'Contract file cost — '.$file->file_number,
                ]
            );
        }

        PaymentSchedule::where('schedulable_type', ContractFile::class)
            ->where('schedulable_id', $file->id)
            ->delete();

        if ($visaRate > 0) {
            if (! $vendorChanged) {
                $this->updatePurchaseJournal(
                    'contract_file',
                    $file->id,
                    TravelCompany::ID,
                    $file->purchase_date ?? $file->payable_date ?? now(),
                    $file->file_number,
                    'Contract file vendor cost — '.$file->file_number,
                    $visaRate,
                    $costPaid,
                    $due,
                    $paymentAccountId,
                    config('accounts.contract_file_cost')
                );
            }

            if ($due > 0 && ! $file->portal_id) {
                PaymentSchedule::create([
                    'company_id' => $companyId,
                    'schedulable_type' => ContractFile::class,
                    'schedulable_id' => $file->id,
                    'type' => 'pay',
                    'party_type' => 'vendor',
                    'party_id' => $file->vendor_id,
                    'party_name' => $file->vendor?->name ?? 'Vendor (unassigned)',
                    'source_label' => $file->file_number,
                    'amount' => $due,
                    'paid_amount' => 0,
                    'scheduled_date' => $file->payable_date?->toDateString() ?? now()->toDateString(),
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);
            }
        } elseif (! $vendorChanged) {
            $this->deletePurchaseJournal('contract_file', $file->id);
        }
    }

    private function createClientPosting(ContractFile $file): void
    {
        if (! $file->client_id || (float) $file->client_price <= 0) {
            return;
        }

        $file->forceFill(['client_due_amount' => max(0, (float) $file->client_price - (float) $file->client_paid_amount)])->saveQuietly();

        $this->reconcilePartyLedgerRow($file->client_id, ContractFile::CLIENT_LEDGER_TYPE, $file->id, true, (float) $file->client_price, [
            'payment_date' => $file->submit_date ?? now(), 'reference_no' => $file->file_number,
            'remarks' => 'Contract file charge — '.$file->file_number,
        ]);
        $this->reconcilePartyLedgerRow($file->client_id, ContractFile::CLIENT_LEDGER_TYPE, $file->id, false, (float) $file->client_paid_amount, [
            'payment_date' => $file->submit_date ?? now(), 'reference_no' => $file->file_number,
            'remarks' => 'Contract file payment — '.$file->file_number,
        ]);
        $this->updateSaleJournal(
            ContractFile::CLIENT_LEDGER_TYPE, $file->id, TravelCompany::ID,
            $file->submit_date ?? now(), $file->file_number, 'Contract file service — '.$file->file_number,
            (float) $file->client_price, (float) $file->client_paid_amount, $file->client_due,
            null, config('accounts.file_sales_revenue'), false,
            $file->client_type === 'agent' ? 'agent' : 'customer', $file->client_id
        );
    }

    private function adjustClientPosting(ContractFile $file, array $before): void
    {
        if ((int) $before['client_id'] === (int) $file->client_id
            && (float) $before['client_price'] === (float) $file->client_price
            && (float) $before['client_paid_amount'] === (float) $file->client_paid_amount) {
            return;
        }

        $oldNet = max(0, (float) $before['client_price'] - (float) $before['client_paid_amount']);
        $newNet = max(0, (float) $file->client_price - (float) $file->client_paid_amount);
        if ($before['client_id'] && $oldNet > 0) {
            $this->postAdjustment((int) $before['client_id'], ContractFile::CLIENT_ADJUSTMENT_TYPE, $file, 0, $oldNet, 'Previous client balance reversed');
        }
        if ($file->client_id && $newNet > 0) {
            $this->postAdjustment((int) $file->client_id, ContractFile::CLIENT_ADJUSTMENT_TYPE, $file, $newNet, 0, 'Updated client balance charged');
        }
        $file->forceFill(['client_due_amount' => $newNet])->saveQuietly();
    }

    private function applyExceptionAdjustments(ContractFile $file, ?string $old, ?string $new): void
    {
        // Explicit refund/re-process actions own money once they exist. Flags are not another refund.
        if ($file->file_changes->isNotEmpty()) {
            return;
        }
        $voiding = in_array($new, ['returned', 'rejected', 'cancelled'], true);
        $wasVoided = in_array($old, ['returned', 'rejected', 'cancelled'], true);
        if ($voiding === $wasVoided) {
            return;
        }

        $clientOutstanding = $voiding ? $file->client_due : max(0, (float) $file->client_price - (float) $file->client_paid_amount);
        $vendorOutstanding = $voiding ? (float) $file->due_amount : max(0, (float) $file->visa_rate - (float) $file->cost_paid_amount);
        if ($file->client_id && $clientOutstanding > 0) {
            $this->postAdjustment($file->client_id, ContractFile::CLIENT_ADJUSTMENT_TYPE, $file,
                $voiding ? 0 : $clientOutstanding, $voiding ? $clientOutstanding : 0,
                $voiding ? 'Client charge reversed' : 'Client charge reinstated');
        }
        if ($file->vendor_id && $vendorOutstanding > 0) {
            $this->postAdjustment($file->vendor_id, ContractFile::VENDOR_ADJUSTMENT_TYPE, $file,
                $voiding ? 0 : $vendorOutstanding, $voiding ? $vendorOutstanding : 0,
                $voiding ? 'Vendor payable reversed' : 'Vendor payable reinstated');
        }
        $file->forceFill([
            'client_due_amount' => $voiding ? 0 : $clientOutstanding,
            'due_amount' => $voiding ? 0 : $vendorOutstanding,
        ])->saveQuietly();
    }

    private function postAdjustment(int $partyId, string $type, ContractFile $file, float $debit, float $credit, string $remarks, ?string $date = null): void
    {
        $this->postPartyLedgerRow($partyId, [
            'type' => $type, 'invoice_id' => $file->id, 'account_id' => null,
            'payment_date' => $date ?? now(), 'reference_no' => $file->file_number,
            'debit' => $debit, 'credit' => $credit, 'remarks' => $remarks.' — '.$file->file_number,
        ]);

        $amount = max($debit, $credit);
        $isClient = $type === ContractFile::CLIENT_ADJUSTMENT_TYPE;
        $control = Account::where('code', config('accounts.'.($isClient ? 'accounts_receivable' : 'accounts_payable')))->firstOrFail();
        $nominal = Account::where('code', config('accounts.'.($isClient ? 'file_sales_revenue' : 'contract_file_cost')))->firstOrFail();
        $reversal = $credit > 0;
        $journal = JournalEntry::create([
            'company_id' => TravelCompany::ID, 'created_by' => Auth::id(), 'date' => $date ?? now(),
            'reference' => $file->file_number, 'source' => $type, 'source_id' => $file->id,
            'description' => $remarks.' — '.$file->file_number,
            'beneficiary' => User::find($partyId)?->name, 'beneficiary_party_id' => $partyId,
        ]);
        $partyType = $isClient ? ($file->client_type === 'agent' ? 'agent' : 'customer') : 'vendor';
        $rows = $isClient
            ? [[$control, ! $reversal, $partyType], [$nominal, $reversal, null]]
            : [[$nominal, ! $reversal, null], [$control, $reversal, $partyType]];
        foreach ($rows as [$account, $debitSide, $itemParty]) {
            JournalItem::create([
                'journal_entry_id' => $journal->id, 'account_id' => $account->id,
                'debit' => $debitSide ? $amount : 0, 'credit' => $debitSide ? 0 : $amount,
                'note' => $remarks, 'party_type' => $itemParty, 'party_id' => $itemParty ? $partyId : null,
            ]);
        }
    }

    private function recordEvent(ContractFile $file, string $type, ?string $code, string $label, ?string $note = null): void
    {
        if (! Schema::hasTable('contract_file_events')) {
            return;
        }
        $file->events()->create([
            'type' => $type, 'code' => $code, 'label' => $label, 'note' => $note,
            'occurred_at' => now(), 'created_by' => Auth::id(),
        ]);
    }
}
