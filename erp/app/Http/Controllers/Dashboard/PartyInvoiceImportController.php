<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\PartyInvoiceImportTemplateExport;
use App\Models\Account;
use App\Models\Company;
use App\Models\PartyInvoiceImportBatch;
use App\Models\User;
use App\Services\CompanyPartyDirectory;
use App\Services\PartyInvoicePostingService;
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

class PartyInvoiceImportController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view party invoice import|view all party invoice import', only: ['template', 'show']),
            new Middleware('permission:create party invoice import', only: ['store']),
            new Middleware('permission:edit party invoice import', only: ['update', 'approve', 'reject']),
        ];
    }

    public function template()
    {
        return Excel::download(new PartyInvoiceImportTemplateExport, 'custom-invoice-import-demo.xlsx');
    }

    public function store(Request $request, string $role)
    {
        $data = $request->validate(['company' => 'required|exists:companies,id', 'invoice_excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $company = $this->company($request, (int) $data['company']);
        try {
            $sheet = (IOFactory::load($request->file('invoice_excel_file')->getRealPath()))->getSheetByName('Invoices');
            if (! $sheet) {
                throw ValidationException::withMessages(['invoice_excel_file' => 'Workbook needs an Invoices sheet.']);
            }
            $dataRows = collect($sheet->toArray(null, true, true, false));
            $headers = collect($dataRows->shift())->map(fn ($value) => Str::snake(trim((string) $value)))->all();
            $missing = array_diff(PartyInvoiceImportTemplateExport::columns(), $headers);
            if ($missing) {
                throw ValidationException::withMessages(['invoice_excel_file' => 'Missing column(s): '.implode(', ', $missing)]);
            }
            $raw = $dataRows->map(fn ($values) => array_combine($headers, array_slice(array_pad((array) $values, count($headers), null), 0, count($headers))))
                ->filter(fn ($row) => collect($row)->contains(fn ($value) => $value !== null && $value !== ''))->values()->all();
            $documents = $this->validateDocuments($raw, $company);
            $batch = PartyInvoiceImportBatch::create(['company_id' => $company->id, 'original_filename' => $request->file('invoice_excel_file')->getClientOriginalName(), 'status' => 'draft', 'documents' => $documents, 'uploaded_by' => $request->user()->id]);

            return redirect()->route('role.party-invoice-import.show', ['role' => $role, 'partyInvoiceImportBatch' => $batch])->with('success', count($documents).' invoice draft(s) created. Nothing posted yet.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['invoice_excel_file' => 'Invoice draft import failed: '.$e->getMessage()]);
        }
    }

    public function show(Request $request, string $role, PartyInvoiceImportBatch $partyInvoiceImportBatch)
    {
        $batch = $this->batch($request, $partyInvoiceImportBatch);

        return view('party-statement.invoice-import-review', compact('batch', 'role'));
    }

    public function update(Request $request, string $role, PartyInvoiceImportBatch $partyInvoiceImportBatch)
    {
        $batch = $this->batch($request, $partyInvoiceImportBatch);
        abort_if($batch->status !== 'draft', 409);
        $rows = collect($request->validate(['documents' => 'required|array|min:1'])['documents'])->flatMap(function ($document, $index) {
            return collect($document['items'] ?? [])->map(fn ($item) => array_merge($document, $item, ['document_group' => $document['document_group'] ?? 'DOC-'.($index + 1)]))->all();
        })->all();
        $batch->update(['documents' => $this->validateDocuments($rows, $batch->company)]);

        return back()->with('success', 'Invoice drafts saved and revalidated.');
    }

    public function approve(Request $request, string $role, PartyInvoiceImportBatch $partyInvoiceImportBatch, PartyInvoicePostingService $posting)
    {
        $batch = $this->batch($request, $partyInvoiceImportBatch);
        abort_if($batch->status !== 'draft', 409);
        try {
            DB::transaction(function () use ($batch, $request, $posting) {
                $locked = PartyInvoiceImportBatch::lockForUpdate()->findOrFail($batch->id);
                if ($locked->status !== 'draft') {
                    throw ValidationException::withMessages(['approval' => 'Batch was already reviewed.']);
                }
                $documents = $this->validateDocuments(collect($locked->documents)->flatMap(fn ($document) => collect($document['items'])->map(fn ($item) => array_merge($document, $item))->all())->all(), $locked->company);
                $ids = [];
                foreach ($documents as $document) {
                    $ids[] = $posting->post($locked->company, User::findOrFail($document['party_id']), $document, $request->user())->id;
                }
                $locked->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'created_invoice_ids' => $ids]);
            });

            return back()->with('success', 'Invoices approved; receivable, revenue, party ledger and journals posted.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['approval' => 'Approval failed and every invoice was rolled back: '.$e->getMessage()]);
        }
    }

    public function reject(Request $request, string $role, PartyInvoiceImportBatch $partyInvoiceImportBatch)
    {
        $batch = $this->batch($request, $partyInvoiceImportBatch);
        abort_if($batch->status !== 'draft', 409);
        $note = $request->validate(['rejection_note' => 'required|string|max:1000'])['rejection_note'];
        $batch->update(['status' => 'rejected', 'rejected_by' => $request->user()->id, 'rejected_at' => now(), 'rejection_note' => $note]);

        return back()->with('success', 'Invoice draft rejected. Nothing posted.');
    }

    private function validateDocuments(array $rows, Company $company): array
    {
        $errors = [];
        $documents = collect($rows)->groupBy(fn ($row) => trim((string) ($row['document_group'] ?? '')))->map(function ($group, $key) use ($company, &$errors) {
            try {
                if ($key === '') {
                    throw new \RuntimeException('document_group is required.');
                }
                $first = $group->first();
                validator($first, ['party_type' => 'required|in:agent,customer', 'party_code_or_name' => 'required', 'invoice_date' => 'required', 'due_date' => 'nullable'])->validate();
                foreach (['party_type', 'party_code_or_name', 'invoice_date', 'due_date'] as $header) {
                    if ($group->pluck($header)->map(fn ($value) => trim((string) $value))->unique()->count() > 1) {
                        throw new \RuntimeException("all rows must use the same {$header}.");
                    }
                }
                $party = $this->party($first['party_code_or_name'], $first['party_type']);
                $directoryType = $first['party_type'] === 'customer' ? 'client' : 'agent';
                if (! $party || ! CompanyPartyDirectory::belongs($party->id, $company->id, $directoryType)) {
                    throw new \RuntimeException('party was not found under the selected company.');
                }
                $items = $group->map(function ($row) use ($company) {
                    validator($row, ['service_type' => 'required|string|max:100', 'description' => 'required|string|max:500', 'passenger_name' => 'nullable|string|max:255', 'quantity' => 'required|numeric|min:0.01', 'unit_price' => 'required|numeric|min:0', 'revenue_account_code' => 'required'])->validate();
                    $account = Account::where('code', $row['revenue_account_code'])->active()->first();
                    if (! $account || $account->type !== 'income' || ($account->company_id && (int) $account->company_id !== (int) $company->id)) {
                        throw new \RuntimeException("revenue account {$row['revenue_account_code']} is invalid.");
                    }

                    return collect(['service_type', 'description', 'passenger_name', 'quantity', 'unit_price', 'revenue_account_code'])->mapWithKeys(fn ($field) => [$field => is_string($row[$field] ?? null) ? trim($row[$field]) : ($row[$field] ?? null)])->all();
                })->values()->all();
                $invoiceDate = $this->date($first['invoice_date']);
                $dueDate = empty($first['due_date']) ? null : $this->date($first['due_date']);
                if ($dueDate && $dueDate < $invoiceDate) {
                    throw new \RuntimeException('due_date cannot be before invoice_date.');
                }

                return ['document_group' => $key, 'party_type' => $first['party_type'], 'party_code_or_name' => $first['party_code_or_name'], 'party_id' => $party->id, 'party_name' => $party->name, 'invoice_date' => $invoiceDate, 'due_date' => $dueDate, 'notes' => $first['notes'] ?? null, 'items' => $items, 'total' => round(collect($items)->sum(fn ($item) => $item['quantity'] * $item['unit_price']), 2)];
            } catch (\Throwable $e) {
                $message = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();
                $errors[] = "Document {$key}: {$message}";

                return null;
            }
        })->filter()->values()->all();
        if ($errors || ! $documents) {
            throw ValidationException::withMessages(['invoice_excel_file' => $errors ?: ['No invoice rows found.']]);
        }

        return $documents;
    }

    private function party(string $value, string $type): ?User
    {
        $role = $type === 'customer' ? 'customer' : 'agent';
        $prefix = $type === 'customer' ? 'EP-CU-' : 'EP-AG-';
        if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/i', trim($value), $match)) {
            return User::role($role)->find((int) $match[1]);
        }

        return User::role($role)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])->first();
    }

    private function date($value): string
    {
        return (is_numeric($value) ? Carbon::instance(ExcelDate::excelToDateTimeObject($value)) : Carbon::parse($value))->format('Y-m-d');
    }

    private function company(Request $request, int $id): Company
    {
        $user = $request->user();

        return Company::whereKey($id)->when(! $user->is_super_admin, fn ($q) => $q->whereKey($user->company_id))->firstOrFail();
    }

    private function batch(Request $request, PartyInvoiceImportBatch $batch): PartyInvoiceImportBatch
    {
        $this->company($request, $batch->company_id);

        return $batch->loadMissing(['company', 'uploader', 'approver', 'rejector']);
    }
}
