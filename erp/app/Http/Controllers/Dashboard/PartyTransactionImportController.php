<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\PartyTransactionImportTemplateExport;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PartyInvoiceImportBatch;
use App\Models\PartyTransactionImportBatch;
use App\Models\User;
use App\Services\CompanyPartyDirectory;
use App\Services\PartyInvoiceAllocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class PartyTransactionImportController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view party transaction import|view all party transaction import|view party invoice import|view all party invoice import', only: ['index']),
            new Middleware('permission:view party transaction import|view all party transaction import', only: ['template', 'show']),
            new Middleware('permission:create party transaction import', only: ['store']),
            new Middleware('permission:edit party transaction import', only: ['update', 'approve', 'reject']),
        ];
    }

    public function index(Request $request)
    {
        $companies = $this->companies($request->user());
        $companyId = $request->user()?->is_super_admin
            ? ($request->integer('company') ?: $companies->first()?->id)
            : $request->user()?->company_id;

        $visibleCompanyIds = $companies->pluck('id');
        $batches = PartyTransactionImportBatch::with(['company', 'uploader', 'approver', 'rejector'])
            ->whereIn('company_id', $visibleCompanyIds)
            ->latest()->limit(25)->get();
        $invoiceBatches = PartyInvoiceImportBatch::with(['company', 'uploader'])
            ->whereIn('company_id', $visibleCompanyIds)
            ->latest()->limit(25)->get();

        return view('party-statement.transaction-import', compact('companies', 'companyId', 'batches', 'invoiceBatches'));
    }

    public function template()
    {
        return Excel::download(new PartyTransactionImportTemplateExport, 'party-transaction-import-demo.xlsx');
    }

    public function store(Request $request, string $role)
    {
        $data = $request->validate([
            'company' => 'required|integer|exists:companies,id',
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'notify_parties' => 'nullable|boolean',
        ]);
        $company = $this->companies($request->user())->firstWhere('id', (int) $data['company']);
        abort_unless($company, 403, 'You cannot post transactions for this company.');
        $request->merge(['suppress_party_notifications' => ! $request->boolean('notify_parties')]);

        try {
            $batch = DB::transaction(function () use ($request, $company) {
                $worksheet = (IOFactory::load($request->file('excel_file')->getRealPath()))->getSheetByName('Transactions');
                if (! $worksheet) {
                    throw ValidationException::withMessages(['excel_file' => 'The workbook needs a sheet named Transactions.']);
                }
                $sheet = collect($worksheet->toArray(null, true, true, false));
                if ($sheet->count() < 2) {
                    throw ValidationException::withMessages(['excel_file' => 'The Transactions sheet has no data rows.']);
                }
                $headers = collect($sheet->shift())->map(fn ($v) => Str::snake(trim((string) $v)))->all();
                $missing = array_diff(PartyTransactionImportTemplateExport::columns(), $headers);
                if ($missing) {
                    throw ValidationException::withMessages(['excel_file' => 'Missing column(s): '.implode(', ', $missing)]);
                }

                $rows = [];
                $errors = [];
                foreach ($sheet as $offset => $values) {
                    $number = $offset + 2;
                    $row = array_combine($headers, array_slice(array_pad((array) $values, count($headers), null), 0, count($headers)));
                    $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
                    if (! array_filter($row, fn ($v) => $v !== null && $v !== '')) {
                        continue;
                    }
                    try {
                        $resolved = $this->resolve($row, $number, $company);
                        $rows[] = ['input' => $row, 'snapshot' => $resolved['display']];
                    } catch (\Throwable $e) {
                        $errors[] = "Row {$number}: {$e->getMessage()}";
                    }
                }
                if (! $rows) {
                    $errors[] = 'No importable transaction rows were found.';
                }
                $duplicateRefs = collect($rows)->pluck('input.external_reference')->duplicates()->unique()->values();
                if ($duplicateRefs->isNotEmpty()) {
                    $errors[] = 'Duplicate external_reference inside this file: '.$duplicateRefs->implode(', ');
                }
                if ($errors) {
                    throw ValidationException::withMessages(['excel_file' => $errors]);
                }

                return PartyTransactionImportBatch::create([
                    'company_id' => $company->id,
                    'original_filename' => $request->file('excel_file')->getClientOriginalName(),
                    'status' => 'draft',
                    'rows' => $rows,
                    'notify_parties' => $request->boolean('notify_parties'),
                    'uploaded_by' => $request->user()->id,
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['excel_file' => 'Import failed: '.$e->getMessage()])->withInput();
        }

        return redirect()->route('role.party-transaction-import.show', ['role' => $role, 'partyTransactionImportBatch' => $batch])
            ->with('success', count($batch->rows).' row(s) validated and saved as a draft. Nothing has been posted yet.');
    }

    public function show(Request $request, string $role, PartyTransactionImportBatch $partyTransactionImportBatch)
    {
        $batch = $this->accessibleBatch($request, $partyTransactionImportBatch);

        return view('party-statement.transaction-import-review', compact('batch', 'role'));
    }

    public function update(Request $request, string $role, PartyTransactionImportBatch $partyTransactionImportBatch)
    {
        $batch = $this->accessibleBatch($request, $partyTransactionImportBatch);
        abort_if($batch->status !== 'draft', 409, 'Only a draft import can be edited.');
        $inputs = $request->validate(['rows' => 'required|array|min:1|max:500'])['rows'];
        $rows = $this->validateInputs($inputs, $batch->company, true);
        $batch->update(['rows' => $rows, 'notify_parties' => $request->boolean('notify_parties')]);

        return back()->with('success', 'Draft saved. No accounting entry has been posted.');
    }

    public function approve(Request $request, string $role, PartyTransactionImportBatch $partyTransactionImportBatch)
    {
        $batch = $this->accessibleBatch($request, $partyTransactionImportBatch);
        abort_if($batch->status !== 'draft', 409, 'This import is no longer awaiting approval.');
        $request->merge(['suppress_party_notifications' => ! $batch->notify_parties]);

        try {
            DB::transaction(function () use ($request, $role, $batch) {
                $locked = PartyTransactionImportBatch::lockForUpdate()->findOrFail($batch->id);
                if ($locked->status !== 'draft') {
                    throw ValidationException::withMessages(['approval' => 'This batch has already been reviewed.']);
                }
                $resolvedRows = collect($locked->rows)->values()->map(fn ($row, $i) => $this->resolve($row['input'], $i + 2, $locked->company))->all();
                foreach ($resolvedRows as $row) {
                    $internal = Request::create($request->url(), 'POST', $row['request'] + ['company' => $locked->company_id]);
                    $internal->setUserResolver(fn () => $request->user());
                    try {
                        $response = app(BankControllerV2::class)->recordTransaction($internal, $role, $row['bank']);
                    } catch (\Throwable $e) {
                        $message = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();
                        throw ValidationException::withMessages(['approval' => "Row {$row['row']}: {$message}"]);
                    }
                    $payload = $response->getData(true);
                    if (! ($payload['success'] ?? false)) {
                        throw ValidationException::withMessages(['approval' => "Row {$row['row']}: ".($payload['message'] ?? 'Posting was rejected.')]);
                    }
                }
                $locked->update([
                    'status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(),
                    'posted_references' => collect($resolvedRows)->pluck('reference')->all(),
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['approval' => 'Approval failed and no row was posted: '.$e->getMessage()]);
        }

        return back()->with('success', 'Import approved and posted successfully.');
    }

    public function reject(Request $request, string $role, PartyTransactionImportBatch $partyTransactionImportBatch)
    {
        $batch = $this->accessibleBatch($request, $partyTransactionImportBatch);
        abort_if($batch->status !== 'draft', 409, 'This import is no longer awaiting approval.');
        $data = $request->validate(['rejection_note' => 'required|string|max:1000']);
        $batch->update(['status' => 'rejected', 'rejected_by' => $request->user()->id, 'rejected_at' => now(), 'rejection_note' => $data['rejection_note']]);

        return back()->with('success', 'Draft rejected. Nothing was posted.');
    }

    private function resolve(array $r, int $rowNumber, Company $company, bool $checkPosted = true): array
    {
        $validation = validator($r, [
            'party_type' => 'required|in:agent,vendor,customer', 'party_code_or_name' => 'required|string|max:255',
            'transaction_kind' => 'required|in:invoice_payment,advance', 'amount' => 'required|numeric|min:0.01',
            'bank_name' => 'required|string|max:255', 'transaction_date' => 'required',
            'transaction_time' => 'required', 'payment_method' => 'required|in:cash,bank,cheque',
            'external_reference' => 'required|string|max:100', 'invoice_reference' => 'nullable|string|max:191',
            'payer_bank' => 'nullable|string|max:150', 'payer_account_or_cheque' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);
        if ($validation->fails()) {
            throw new \RuntimeException($validation->errors()->first());
        }

        $type = $r['party_type'] === 'customer' ? 'client' : $r['party_type'];
        $party = $this->party($r['party_code_or_name'], $type);
        if (! $party || ! CompanyPartyDirectory::belongs($party->id, $company->id, $type)) {
            throw new \RuntimeException("{$r['party_type']} '{$r['party_code_or_name']}' was not found under {$company->name}.");
        }
        $bank = Bank::where('company_id', $company->id)->where(fn ($query) => $query->where('status', 1)->orWhereNull('status'))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($r['bank_name'])])->first();
        if (! $bank) {
            throw new \RuntimeException("bank '{$r['bank_name']}' was not found under {$company->name}.");
        }
        if (! $bank->account_id) {
            throw new \RuntimeException("bank '{$bank->name}' is not linked to a chart-of-accounts account.");
        }
        if ($checkPosted && JournalEntry::withTrashed()->where('company_id', $company->id)->where('reference', $r['external_reference'])->exists()) {
            throw new \RuntimeException("external_reference '{$r['external_reference']}' has already been posted.");
        }

        $receivable = $type !== 'vendor';
        $kind = $r['transaction_kind'];
        $allocation = [];
        $matchedInvoice = null;
        if ($kind === 'invoice_payment') {
            if (empty($r['invoice_reference'])) {
                throw new \RuntimeException('invoice_reference is required for invoice_payment.');
            }
            $side = $receivable ? 'sale' : 'payable';
            $matches = app(PartyInvoiceAllocation::class)->unpaidForParty($party->id, $side, $company->id)
                ->filter(fn ($invoice) => mb_strtolower(trim((string) $invoice['number'])) === mb_strtolower($r['invoice_reference']))->values();
            if ($matches->count() !== 1) {
                throw new \RuntimeException("invoice/bill '{$r['invoice_reference']}' was not found as one exact unpaid document for this party.");
            }
            if ((float) $r['amount'] > (float) $matches[0]['due'] + .001) {
                throw new \RuntimeException('amount exceeds the document due of '.number_format((float) $matches[0]['due'], 2).'.');
            }
            $matchedInvoice = $matches[0];
            $allocation = [['key' => $matches[0]['value'], 'amount' => (float) $r['amount']]];
            $accountCode = $receivable ? config('accounts.accounts_receivable') : config('accounts.accounts_payable');
        } else {
            if (! empty($r['invoice_reference'])) {
                throw new \RuntimeException('invoice_reference must be blank for an advance.');
            }
            $accountCode = $receivable ? config('accounts.customer_advance') : config('accounts.supplier_advance');
        }
        $contra = Account::where('code', $accountCode)->first();
        if (! $contra) {
            throw new \RuntimeException("configured account {$accountCode} is missing from the chart of accounts.");
        }

        $date = $this->date($r['transaction_date']);
        $time = $this->time($r['transaction_time']);

        return ['row' => $rowNumber, 'reference' => $r['external_reference'], 'bank' => $bank, 'display' => [
            'party_type' => $r['party_type'],
            'party_name' => $party->name,
            'party_code' => ['agent' => 'EP-AG-', 'vendor' => 'EP-VN-', 'client' => 'EP-CU-'][$type].$party->id,
            'bank_name' => $bank->name,
            'document' => $matchedInvoice['number'] ?? null,
            'document_due' => isset($matchedInvoice['due']) ? (float) $matchedInvoice['due'] : null,
            'posting' => $receivable
                ? 'Debit '.$bank->name.' / Credit '.$contra->name
                : 'Debit '.$contra->name.' / Credit '.$bank->name,
        ], 'request' => [
            'type' => $receivable ? 'deposit' : 'withdraw', 'amount' => $r['amount'], 'date' => $date, 'time' => $time,
            'contra_account_id' => $contra->id, 'reference' => $r['external_reference'], 'beneficiary_party_id' => $party->id,
            'purpose' => $r['notes'] ?: ($kind === 'advance' ? 'Advance payment' : 'Invoice payment '.$r['invoice_reference']),
            'pay_method' => $r['payment_method'], 'pay_bank' => $r['payer_bank'] ?: null,
            'pay_ref' => $r['payer_account_or_cheque'] ?: null, 'allocation_mode' => $kind === 'advance' ? 'unallocated' : 'manual',
            'invoice_allocations' => $allocation,
        ]];
    }

    private function party(string $value, string $type): ?User
    {
        $role = $type === 'client' ? 'customer' : $type;
        $prefix = ['agent' => 'EP-AG-', 'vendor' => 'EP-VN-', 'client' => 'EP-CU-'][$type];
        if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/i', trim($value), $match)) {
            return User::role($role)->find((int) $match[1]);
        }

        return User::role($role)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])->first();
    }

    private function date($value): string
    {
        try {
            return (is_numeric($value) ? Carbon::instance(ExcelDate::excelToDateTimeObject($value)) : Carbon::parse($value))->format('Y-m-d');
        } catch (\Throwable) {
            throw new \RuntimeException("invalid transaction_date '{$value}'.");
        }
    }

    private function time($value): string
    {
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('H:i');
            }

            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            throw new \RuntimeException("invalid transaction_time '{$value}'. Use HH:MM.");
        }
    }

    private function companies(?User $user)
    {
        return Company::where('status', 1)->when(! $user?->is_super_admin, fn ($q) => $q->whereKey($user?->company_id))
            ->orderBy('name')->get(['id', 'name']);
    }

    private function accessibleBatch(Request $request, PartyTransactionImportBatch $batch): PartyTransactionImportBatch
    {
        abort_unless($this->companies($request->user())->contains('id', $batch->company_id), 403);

        return $batch->loadMissing(['company', 'uploader', 'approver', 'rejector']);
    }

    private function validateInputs(array $inputs, Company $company, bool $checkPosted): array
    {
        $rows = [];
        $errors = [];
        foreach (array_values($inputs) as $index => $input) {
            $number = $index + 2;
            $input = collect(PartyTransactionImportTemplateExport::columns())
                ->mapWithKeys(fn ($column) => [$column => is_string($input[$column] ?? null) ? trim($input[$column]) : ($input[$column] ?? null)])
                ->all();
            try {
                $resolved = $this->resolve($input, $number, $company, $checkPosted);
                $rows[] = ['input' => $input, 'snapshot' => $resolved['display']];
            } catch (\Throwable $e) {
                $errors[] = "Row {$number}: {$e->getMessage()}";
            }
        }
        $duplicateRefs = collect($rows)->pluck('input.external_reference')->duplicates()->unique()->values();
        if ($duplicateRefs->isNotEmpty()) {
            $errors[] = 'Duplicate external_reference: '.$duplicateRefs->implode(', ');
        }
        if ($errors) {
            throw ValidationException::withMessages(['rows' => $errors]);
        }

        return $rows;
    }
}
