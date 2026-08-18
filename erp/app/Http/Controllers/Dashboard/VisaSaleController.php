<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Mail\VoucherMail;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Country;
use App\Models\InvoiceTemplate;
use App\Models\OtherVisaService;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Models\VisaProcess;
use App\Models\VisaSale;
use App\Models\VisaSaleItem;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsSaleJournal;
use App\Traits\ResolvesPartyType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class VisaSaleController extends Controller
{
    use PostsPartyLedger, PostsSaleJournal, ResolvesPartyType;

    public function index(Request $request)
    {
        $now = now();

        $mtdSales = VisaSale::whereYear('voucher_date', $now->year)
            ->whereMonth('voucher_date', $now->month);

        $stats = [
            'total_sales_mtd' => (clone $mtdSales)->count(),
            'revenue_mtd'     => (clone $mtdSales)->sum('total_amount'),
            'total_due'       => VisaSale::where('status', '!=', 'paid')->sum('due_amount'),
            'commission'      => VisaSaleItem::join('visa_processes', 'visa_sale_items.visa_process_id', '=', 'visa_processes.id')
                ->selectRaw('SUM(visa_sale_items.sale_price - visa_processes.costing_price) as profit')
                ->value('profit') ?? 0,
        ];

        $query = VisaSale::with([
            'items.visaProcess.country',
            'items.visaProcess.visaCategory',
            'issuedBy',
            'client',
        ]);

        $query->when($request->filled('search'), fn($q) =>
            $q->where(function ($inner) use ($request) {
                $inner->where('invoice_number', 'like', "%{$request->search}%")
                      ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$request->search}%")
                          ->orWhere('phone', 'like', "%{$request->search}%"));
            })
        );

        $query->when($request->filled('status'), fn($q) =>
            $q->where('status', $request->status)
        );

        $query->when($request->filled('month'), function ($q) use ($request) {
            [$year, $month] = explode('-', $request->month);
            $q->whereYear('voucher_date', $year)->whereMonth('voucher_date', $month);
        });

        $sales  = $query->latest()->paginate(15)->withQueryString();
        $users  = User::orderBy('name')->role('visa')->get(['id', 'name']);
        $clients = User::orderBy('name')->role('customer')->get(['id', 'name', 'phone', 'email']);
        $agents  = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['vendor', 'agent']))
            ->orderBy('name')->get(['id', 'name', 'phone', 'email']);
        $nextInvoice = VisaSale::nextInvoiceNumber();
        $banks = Bank::where('status', 1)->get();

        return view('visa-sales.index', compact('sales', 'stats', 'users', 'clients', 'agents', 'nextInvoice', 'banks'));
    }

    public function fetchApplications(Request $request)
    {
        $alreadyBundled = VisaSaleItem::whereNotNull('visa_process_id')->pluck('visa_process_id');

        // When editing an existing sale, its own items should remain selectable
        if ($request->filled('edit_sale_id')) {
            $ownIds = VisaSaleItem::where('visa_sale_id', $request->integer('edit_sale_id'))->pluck('visa_process_id');
            $alreadyBundled = $alreadyBundled->diff($ownIds);
        }

        $query = VisaProcess::with(['country', 'visaCategory', 'passportHolder'])
            ->whereNotIn('id', $alreadyBundled);

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->boolean('show_unpaid')) {
            $query->whereIn('payment_status', ['pending', 'partial']);
        }

        $processes = $query->latest()->get()->map(fn($p) => [
            'id'        => $p->id,
            'app_id'    => $p->application_id,
            'applicant' => optional($p->passportHolder)->name ?? '—',
            'country'   => optional($p->country)->name ?? '—',
            'visa_type' => $p->visa_type ?? (optional($p->visaCategory)->name ?? '—'),
            'sale_price' => $p->sale_price,
            'status'    => $p->payment_status,
        ]);

        // Country filter options should list every country used by any visa process,
        // not just ones with currently unbundled applications, otherwise the dropdown
        // can end up empty once all applications for a country have been bundled.
        $countryIds = VisaProcess::whereNotNull('country_id')
            ->distinct()
            ->pluck('country_id');

        $countries = Country::whereIn('id', $countryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'applications' => $processes,
            'countries'    => $countries,
        ]);
    }

    /**
     * Other (non-core) billable services available to bundle into a voucher.
     */
    public function fetchOtherServices(Request $request)
    {
        $alreadyBundled = VisaSaleItem::whereNotNull('other_visa_service_id')->pluck('other_visa_service_id');

        // When editing an existing sale, its own items should remain selectable
        if ($request->filled('edit_sale_id')) {
            $ownIds = VisaSaleItem::where('visa_sale_id', $request->integer('edit_sale_id'))
                ->whereNotNull('other_visa_service_id')
                ->pluck('other_visa_service_id');
            $alreadyBundled = $alreadyBundled->diff($ownIds);
        }

        $services = OtherVisaService::with(['passportHolder', 'serviceType'])
            ->where('is_billable', true)
            ->whereNotIn('id', $alreadyBundled)
            ->latest()
            ->get()
            ->map(fn($s) => [
                'id'            => $s->id,
                'service_code'  => $s->service_code,
                'applicant'     => optional($s->passportHolder)->name ?? '—',
                'service_type'  => optional($s->serviceType)->name ?? '—',
                'service_color' => optional($s->serviceType)->color ?? '#64748b',
                'service_bg'    => optional($s->serviceType)->bg_color ?? '#f1f5f9',
                'sale_price'    => $s->sale_price,
                'status'        => $s->status,
            ]);

        return response()->json([
            'services' => $services,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id'          => 'required|exists:users,id',
            'send_via'           => 'required|in:email,sms,whatsapp',
            'bundle_label'       => 'nullable|string|max:100',
            'voucher_date'       => 'required|date',
            'receivable_date'    => 'nullable|date',
            'issued_by'          => 'nullable|exists:users,id',
            'visa_process_ids'   => 'nullable|array',
            'visa_process_ids.*' => 'exists:visa_processes,id',
            'other_service_ids'   => 'nullable|array',
            'other_service_ids.*' => 'exists:other_visa_services,id',
            'paid_amount'        => 'required|numeric|min:0',
            'payment_method'     => 'nullable|string|max:50',
            'bank_id'            => 'nullable|exists:banks,id',
            'notes'              => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (empty($request->visa_process_ids) && empty($request->other_service_ids)) {
                $validator->errors()->add('visa_process_ids', 'Please select at least one application or other service to bundle.');
            }
        });

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $processes     = VisaProcess::whereIn('id', $request->visa_process_ids ?? [])->get();
        $otherServices = OtherVisaService::whereIn('id', $request->other_service_ids ?? [])->get();
        $totalAmount   = $processes->sum('sale_price') + $otherServices->sum('sale_price');
        $paidAmount    = min((float) $request->paid_amount, $totalAmount);
        $dueAmount     = max(0, $totalAmount - $paidAmount);
        $status        = $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');

        $sale = DB::transaction(function () use ($request, $processes, $otherServices, $totalAmount, $paidAmount, $dueAmount, $status) {
            $sale = VisaSale::create([
                'company_id'     => Auth::user()->company_id ?? 2,
                'invoice_number' => VisaSale::nextInvoiceNumber(),
                'client_id'      => $request->client_id,
                'send_via'       => $request->send_via,
                'bundle_label'   => $request->bundle_label,
                'voucher_date'    => $request->voucher_date,
                'receivable_date' => $request->receivable_date,
                'issued_by'      => $request->issued_by,
                'total_amount'   => $totalAmount,
                'paid_amount'    => $paidAmount,
                'due_amount'     => $dueAmount,
                'payment_method' => $request->payment_method,
                'bank_id'        => $request->bank_id,
                'notes'          => $request->notes,
                'status'         => $status,
                'created_by'     => Auth::id(),
            ]);

            foreach ($processes as $process) {
                VisaSaleItem::create([
                    'visa_sale_id'    => $sale->id,
                    'visa_process_id' => $process->id,
                    'sale_price'      => $process->sale_price,
                ]);
            }

            foreach ($otherServices as $service) {
                VisaSaleItem::create([
                    'visa_sale_id'           => $sale->id,
                    'other_visa_service_id'  => $service->id,
                    'sale_price'             => $service->sale_price,
                ]);
            }

            $this->reconcileVisaSaleLedger($sale, null);
            $this->syncVisaSaleJournal($sale, 'Visa sale — ' . $sale->invoice_number);
            $this->syncVisaSaleReceivableSchedule($sale);

            return $sale;
        });

        $sale->load('client');
        $emailStatus = null;
        if ($sale->send_via === 'email' && $sale->client?->email) {
            $emailStatus = $this->sendVoucherEmail($sale, $sale->client->email);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sale voucher created successfully.' . ($emailStatus === false ? ' (Voucher email failed to send.)' : ''),
        ]);
    }

    public function makePayment(Request $request, $role, VisaSale $visaSale)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
            'bank_id'        => 'nullable|exists:banks,id',
        ]);

        $newPaid = $visaSale->paid_amount + (float) $request->payment_amount;
        $newPaid = min($newPaid, $visaSale->total_amount);
        $newDue  = max(0, $visaSale->total_amount - $newPaid);
        $status  = $newDue <= 0 ? 'paid' : 'partial';

        DB::transaction(function () use ($request, $visaSale, $newPaid, $newDue, $status) {
            $visaSale->update([
                'paid_amount'    => $newPaid,
                'due_amount'     => $newDue,
                'status'         => $status,
                'payment_method' => $request->payment_method ?? $visaSale->payment_method,
                'bank_id'        => $request->bank_id ?? $visaSale->bank_id,
            ]);

            if ($visaSale->client_id) {
                $this->reconcilePartyBankBalance('visa_sale', $visaSale->id, $visaSale->bank_id, (float) $newPaid);

                $this->reconcilePartyLedgerRow(
                    $visaSale->client_id, 'visa_sale', $visaSale->id, false, (float) $newPaid,
                    [
                        'payment_date'   => now()->toDateString(),
                        'reference_no'   => $visaSale->invoice_number,
                        'remarks'        => 'Visa sale payment received — ' . $visaSale->invoice_number,
                        'account_id'     => $visaSale->bank_id,
                        'payment_method' => $visaSale->payment_method,
                    ]
                );
            }

            if ($request->bank_id && $request->payment_amount > 0) {
                $this->postSaleJournalPayment(
                    'visa_sale',
                    $visaSale->id,
                    $visaSale->company_id ?? (Auth::user()->company_id ?? 2),
                    now(),
                    $visaSale->invoice_number,
                    'Visa sale payment received — ' . $visaSale->invoice_number,
                    (float) $request->payment_amount,
                    $request->bank_id
                );
            }
        });

        return back()->with('success', 'Payment of ৳' . number_format($request->payment_amount, 2) . ' recorded.');
    }

    /**
     * Keep the sale's invoice/paid rows on the client's party-statement
     * ledger in sync. If the client changed since the last save, the old
     * client's rows are dropped and fresh ones posted against the new
     * client rather than cascade-repairing the old chain.
     */
    private function reconcileVisaSaleLedger(VisaSale $sale, ?int $oldClientId): void
    {
        if ($oldClientId && $oldClientId != $sale->client_id) {
            $this->reversePartyLedger('visa_sale', $sale->id);
        }

        if (!$sale->client_id) {
            return;
        }

        $common = [
            'payment_date' => now()->toDateString(),
            'reference_no' => $sale->invoice_number,
        ];

        $this->reconcilePartyBankBalance('visa_sale', $sale->id, $sale->bank_id, (float) $sale->paid_amount);

        $this->reconcilePartyLedgerRow(
            $sale->client_id, 'visa_sale', $sale->id, true, (float) $sale->total_amount,
            $common + ['remarks' => 'Visa sale invoice — ' . $sale->invoice_number]
        );

        $this->reconcilePartyLedgerRow(
            $sale->client_id, 'visa_sale', $sale->id, false, (float) $sale->paid_amount,
            $common + [
                'remarks'        => 'Visa sale payment received — ' . $sale->invoice_number,
                'account_id'     => $sale->bank_id,
                'payment_method' => $sale->payment_method,
            ]
        );
    }

    /**
     * Keep the sale's chart-of-accounts journal entry in sync (creates it on
     * first save, replaces its items on every later save).
     */
    private function syncVisaSaleJournal(VisaSale $sale, string $description): void
    {
        $this->updateSaleJournal(
            'visa_sale',
            $sale->id,
            $sale->company_id ?? (Auth::user()->company_id ?? 2),
            now(),
            $sale->invoice_number,
            $description,
            (float) $sale->total_amount,
            (float) $sale->paid_amount,
            (float) $sale->due_amount,
            $sale->bank_id,
            config('accounts.visa_sales_revenue')
        );
    }

    /**
     * Keep the sale's receivable PaymentSchedule row in sync with its
     * current due amount, keyed to the real paying client — not the
     * traveler/passport holder.
     */
    private function syncVisaSaleReceivableSchedule(VisaSale $sale): void
    {
        PaymentSchedule::where('schedulable_type', VisaSale::class)
            ->where('schedulable_id', $sale->id)
            ->delete();

        if ((float) $sale->due_amount <= 0) {
            return;
        }

        $sale->loadMissing('client');

        PaymentSchedule::create([
            'company_id'       => $sale->company_id,
            'schedulable_type' => VisaSale::class,
            'schedulable_id'   => $sale->id,
            'type'             => 'receive',
            'party_type'       => $this->resolvePartyType($sale->client),
            'party_id'         => $sale->client_id,
            'party_name'       => $sale->client?->name,
            'source_label'     => $sale->invoice_number,
            'amount'           => (float) $sale->due_amount,
            'paid_amount'      => (float) $sale->paid_amount,
            'scheduled_date'   => $sale->receivable_date?->toDateString() ?? $sale->voucher_date?->toDateString() ?? now()->toDateString(),
            'status'           => 'pending',
            'created_by'       => Auth::id(),
        ]);
    }

    public function show($role, VisaSale $visaSale)
    {
        $visaSale->load(
            'items.visaProcess.country',
            'items.visaProcess.visaCategory',
            'items.visaProcess.passportHolder',
            'items.otherVisaService.passportHolder',
            'items.otherVisaService.serviceType',
            'issuedBy',
            'client'
        );

        $items = $visaSale->items->map(function ($item) {
            if ($item->other_visa_service_id) {
                $service = $item->otherVisaService;
                return [
                    'line_type'   => 'other_service',
                    'app_id'      => optional($service)->service_code,
                    'applicant'   => optional(optional($service)->passportHolder)->name ?? '—',
                    'passport_no' => optional(optional($service)->passportHolder)->passport_no ?? '',
                    'country'     => optional(optional($service)->serviceType)->name ?? '—',
                    'visa_type'   => 'Other Service',
                    'sale_price'  => $item->sale_price,
                    'status'      => optional($service)->status ?? 'pending',
                ];
            }

            return [
                'line_type'   => 'visa_process',
                'app_id'      => optional($item->visaProcess)->application_id,
                'applicant'   => optional(optional($item->visaProcess)->passportHolder)->name ?? '—',
                'passport_no' => optional(optional($item->visaProcess)->passportHolder)->passport_no ?? '',
                'country'     => optional(optional($item->visaProcess)->country)->name ?? '—',
                'visa_type'   => optional($item->visaProcess)->visa_type
                               ?? optional(optional($item->visaProcess)->visaCategory)->name ?? '—',
                'sale_price'  => $item->sale_price,
                'status'      => optional($item->visaProcess)->status ?? 'pending',
            ];
        });

        return response()->json([
            'id'             => $visaSale->id,
            'invoice_number' => $visaSale->invoice_number,
            'client_id'      => $visaSale->client_id,
            'client_name'    => $visaSale->client?->name,
            'client_phone'   => $visaSale->client?->phone,
            'client_email'   => $visaSale->client?->email,
            'voucher_date'   => $visaSale->voucher_date->format('d M Y'),
            'bundle_label'   => $visaSale->bundle_label,
            'total_amount'   => $visaSale->total_amount,
            'paid_amount'    => $visaSale->paid_amount,
            'due_amount'     => $visaSale->due_amount,
            'status'         => $visaSale->status,
            'notes'          => $visaSale->notes,
            'issued_by'      => optional($visaSale->issuedBy)->name,
            'items'          => $items,
        ]);
    }

    public function edit($role, VisaSale $visaSale)
    {
        $visaSale->load('items', 'client');

        return response()->json([
            'id'               => $visaSale->id,
            'invoice_number'   => $visaSale->invoice_number,
            'client_id'        => $visaSale->client_id,
            'client_name'      => $visaSale->client?->name,
            'client_phone'     => $visaSale->client?->phone,
            'client_email'     => $visaSale->client?->email,
            'send_via'         => $visaSale->send_via,
            'bundle_label'     => $visaSale->bundle_label ?? '',
            'voucher_date'     => $visaSale->voucher_date->format('Y-m-d'),
            'receivable_date'  => $visaSale->receivable_date?->format('Y-m-d') ?? '',
            'issued_by'        => $visaSale->issued_by ?? '',
            'paid_amount'      => $visaSale->paid_amount,
            'payment_method'   => $visaSale->payment_method ?? '',
            'bank_id'          => $visaSale->bank_id,
            'notes'            => $visaSale->notes ?? '',
            'visa_process_ids' => $visaSale->items->pluck('visa_process_id')->filter()->values()->toArray(),
            'other_service_ids' => $visaSale->items->pluck('other_visa_service_id')->filter()->values()->toArray(),
        ]);
    }

    public function update(Request $request, $role, VisaSale $visaSale)
    {
        $validator = Validator::make($request->all(), [
            'client_id'           => 'required|exists:users,id',
            'send_via'            => 'required|in:email,sms,whatsapp',
            'bundle_label'        => 'nullable|string|max:100',
            'voucher_date'        => 'required|date',
            'receivable_date'     => 'nullable|date',
            'issued_by'           => 'nullable|exists:users,id',
            'visa_process_ids'    => 'nullable|array',
            'visa_process_ids.*'  => 'exists:visa_processes,id',
            'other_service_ids'   => 'nullable|array',
            'other_service_ids.*' => 'exists:other_visa_services,id',
            'paid_amount'         => 'required|numeric|min:0',
            'payment_method'      => 'nullable|string|max:50',
            'bank_id'             => 'nullable|exists:banks,id',
            'notes'               => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (empty($request->visa_process_ids) && empty($request->other_service_ids)) {
                $validator->errors()->add('visa_process_ids', 'Please select at least one application or other service to bundle.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $processes     = VisaProcess::whereIn('id', $request->visa_process_ids ?? [])->get();
        $otherServices = OtherVisaService::whereIn('id', $request->other_service_ids ?? [])->get();
        $totalAmount   = $processes->sum('sale_price') + $otherServices->sum('sale_price');
        $paidAmount    = min((float) $request->paid_amount, $totalAmount);
        $dueAmount     = max(0, $totalAmount - $paidAmount);
        $status        = $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');

        DB::transaction(function () use ($request, $visaSale, $processes, $otherServices, $totalAmount, $paidAmount, $dueAmount, $status) {
            $oldClientId = $visaSale->client_id;

            $visaSale->update([
                'client_id'     => $request->client_id,
                'send_via'      => $request->send_via,
                'bundle_label'  => $request->bundle_label,
                'voucher_date'    => $request->voucher_date,
                'receivable_date' => $request->receivable_date,
                'issued_by'     => $request->issued_by,
                'total_amount'  => $totalAmount,
                'paid_amount'   => $paidAmount,
                'due_amount'    => $dueAmount,
                'payment_method' => $request->payment_method,
                'bank_id'       => $request->bank_id,
                'notes'         => $request->notes,
                'status'        => $status,
            ]);

            $this->reconcileVisaSaleLedger($visaSale, $oldClientId);
            $this->syncVisaSaleJournal($visaSale, 'Visa sale (edited) — ' . $visaSale->invoice_number);
            $this->syncVisaSaleReceivableSchedule($visaSale);

            VisaSaleItem::where('visa_sale_id', $visaSale->id)->delete();

            foreach ($processes as $process) {
                VisaSaleItem::create([
                    'visa_sale_id'    => $visaSale->id,
                    'visa_process_id' => $process->id,
                    'sale_price'      => $process->sale_price,
                ]);
            }

            foreach ($otherServices as $service) {
                VisaSaleItem::create([
                    'visa_sale_id'          => $visaSale->id,
                    'other_visa_service_id' => $service->id,
                    'sale_price'            => $service->sale_price,
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Sale updated successfully.']);
    }

    public function destroy($role, VisaSale $visaSale)
    {
        DB::transaction(function () use ($visaSale) {
            $this->reversePartyLedger('visa_sale', $visaSale->id);
            $this->deleteSaleJournal('visa_sale', $visaSale->id);
            PaymentSchedule::where('schedulable_type', VisaSale::class)
                ->where('schedulable_id', $visaSale->id)
                ->delete();
            // Free up the bundled applications/other services so they can be
            // added to a new sale — otherwise fetchApplications()/
            // fetchOtherServices() keep excluding them forever.
            VisaSaleItem::where('visa_sale_id', $visaSale->id)->delete();
            $visaSale->delete();
        });

        return response()->json(['success' => true, 'message' => 'Sale deleted.']);
    }

    public function voucherPrint($role, VisaSale $visaSale)
    {
        $visaSale->load(
            'items.visaProcess.country',
            'items.visaProcess.visaCategory',
            'items.visaProcess.passportHolder',
            'items.otherVisaService.passportHolder',
            'items.otherVisaService.serviceType',
            'issuedBy',
            'client'
        );

        $company = $visaSale->company ?? Company::find(2);

        $invoiceTemplate = InvoiceTemplate::with('fields', 'style')
            ->where('type', 'visa_sale')->where('is_default', 1)->first()
            ?? InvoiceTemplate::with('fields', 'style')->where('is_default', 1)->first();

        return view('visa-sales.voucher', compact('visaSale', 'company', 'invoiceTemplate'));
    }

    /**
     * Manually (re)send the voucher PDF to an email address.
     */
    public function emailVoucher(Request $request, $role, VisaSale $visaSale)
    {
        $request->validate([
            'email' => 'nullable|email',
        ]);

        $email = $request->input('email') ?: $visaSale->client?->email;

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'No email address available for this client.',
            ]);
        }

        $sent = $this->sendVoucherEmail($visaSale, $email);

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Voucher emailed to ' . $email . '.' : 'Failed to send the voucher email.',
        ]);
    }

    /**
     * Render the voucher as a PDF and email it to the given address.
     */
    private function sendVoucherEmail(VisaSale $visaSale, string $email): bool
    {
        try {
            $visaSale->load(
            'items.visaProcess.country',
            'items.visaProcess.visaCategory',
            'items.visaProcess.passportHolder',
            'items.otherVisaService.passportHolder',
            'items.otherVisaService.serviceType',
            'issuedBy',
            'client'
        );
            $company = $visaSale->company ?? Company::find(2);
            $invoiceTemplate = InvoiceTemplate::with('fields', 'style')
                ->where('type', 'visa_sale')->where('is_default', 1)->first()
                ?? InvoiceTemplate::with('fields', 'style')->where('is_default', 1)->first();

            $pdf = Pdf::loadView('visa-sales.voucher', compact('visaSale', 'company', 'invoiceTemplate'));

            Mail::to($email)->send(new VoucherMail($visaSale, $pdf->output()));

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}
