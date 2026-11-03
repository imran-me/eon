<?php

namespace App\Traits;

use App\Models\Account;
use App\Models\Bank;
use App\Models\ContractFile;
use App\Models\ContractFileCategory;
use App\Models\ContractFileDocument;
use App\Models\ContractFileServiceCost;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;
use App\Services\ContractFileSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait CreatesContractFileBatch
{
    private function storeBatch(Request $request)
    {
        abort_unless(Schema::hasTable('contract_file_service_costs') && Schema::hasTable('contract_file_documents'), 503, 'File builder database update is required.');
        $otherRules = [];
        foreach (['enabled' => 'nullable|boolean', 'title' => 'nullable|string|max:160', 'vendor_id' => 'nullable|integer|exists:users,id', 'amount' => 'nullable|numeric|min:0|max:999999999', 'paid_amount' => 'nullable|numeric|min:0|max:999999999', 'bank_id' => 'nullable|integer|exists:banks,id', 'incurred_on' => 'nullable|date', 'payment_date' => 'nullable|date', 'due_date' => 'nullable|date'] as $key => $rule) {
            $otherRules['applicants.*.others.'.$key] = $rule;
        }
        // Setup (2026-10-25): a file may carry a visa type — only once Setup's migration has run.
        $setup = ContractFileSetup::installed();
        if ($setup) {
            $otherRules['applicants.*.contract_file_visa_type_id'] = ['nullable', 'integer', Rule::exists('contract_file_visa_types', 'id')->whereNull('deleted_at')];
        }
        $data = $request->validate(array_merge([
            'request_token' => 'required|uuid', 'applicants' => 'required|array|min:1|max:30',
            'applicants.*.passport_holder_id' => 'required|integer|distinct|exists:passport_holders,id',
            'applicants.*.client_id' => 'required|integer|exists:users,id', 'applicants.*.client_type' => 'required|in:agent,direct',
            'applicants.*.country_id' => 'required|integer|exists:countries,id', 'applicants.*.contract_file_category_id' => 'required|integer|exists:contract_file_categories,id',
            'applicants.*.vendor_id' => 'required|integer|exists:users,id', 'applicants.*.client_price' => 'required|numeric|min:0|max:999999999',
            'applicants.*.visa_rate' => 'required|numeric|min:0|max:999999999', 'applicants.*.submit_date' => 'required|date',
            'applicants.*.cost_paid_amount' => 'nullable|numeric|min:0|max:999999999', 'applicants.*.cost_bank_id' => 'nullable|integer|exists:banks,id',
            'applicants.*.purchase_date' => 'nullable|date', 'applicants.*.payable_date' => 'nullable|date',
            'applicants.*.expected_out_date' => 'nullable|date', 'applicants.*.notes' => 'nullable|string|max:5000',
            'applicants.*.documents' => 'nullable|array|max:20', 'applicants.*.documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'applicants.*.linked_documents' => 'nullable|array|max:20', 'applicants.*.linked_documents.*' => 'integer|distinct|exists:contract_file_documents,id',
            'applicants.*.received_documents' => 'nullable|array', 'applicants.*.received_documents.*' => 'string|max:255',
            'applicants.*.training.enabled' => 'nullable|boolean', 'applicants.*.training.vendor_id' => 'nullable|integer|exists:users,id',
            'applicants.*.training.amount' => 'nullable|numeric|min:0|max:999999999', 'applicants.*.training.paid_amount' => 'nullable|numeric|min:0|max:999999999',
            'applicants.*.training.bank_id' => 'nullable|integer|exists:banks,id', 'applicants.*.training.incurred_on' => 'nullable|date',
            'applicants.*.training.payment_date' => 'nullable|date', 'applicants.*.training.due_date' => 'nullable|date',
        ], $otherRules));
        foreach ($data['applicants'] as $i => $row) {
            $category = ContractFileCategory::findOrFail($row['contract_file_category_id']);
            if ($category->country_id && (int) $category->country_id !== (int) $row['country_id']) {
                throw ValidationException::withMessages(["applicants.$i.contract_file_category_id" => 'Category does not belong to this country.']);
            }
            $training = $row['training'] ?? [];
            $others = $row['others'] ?? [];
            if (! empty($others['enabled'])) {
                if (empty(trim($others['title'] ?? '')) || empty($others['vendor_id'])) {
                    throw ValidationException::withMessages(["applicants.$i.others" => 'Other service requires a title and provider.']);
                }
                if (($others['paid_amount'] ?? 0) > ($others['amount'] ?? 0) || (($others['paid_amount'] ?? 0) > 0 && (empty($others['bank_id']) || empty($others['payment_date'])))) {
                    throw ValidationException::withMessages(["applicants.$i.others" => 'Other service payment must not exceed cost and requires a bank and payment date.']);
                }
            }
            if (($row['cost_paid_amount'] ?? 0) > $row['visa_rate'] || (($row['cost_paid_amount'] ?? 0) > 0 && empty($row['cost_bank_id']))) {
                throw ValidationException::withMessages(["applicants.$i.cost_paid_amount" => 'Processing payment must not exceed cost and requires a bank.']);
            }
            if (! empty($training['enabled'])) {
                $provider = User::find($training['vendor_id'] ?? null);
                $eligible = $provider && ($provider->hasAnyRole(['trainer', 'consultant']) || (in_array('instructor', (array) $provider->service_categories, true) && $provider->hasAnyRole(['agent', 'vendor'])));
                if (! $eligible) {
                    throw ValidationException::withMessages(["applicants.$i.training.vendor_id" => 'Select an eligible trainer.']);
                }
                if (($training['paid_amount'] ?? 0) > ($training['amount'] ?? 0)) {
                    throw ValidationException::withMessages(["applicants.$i.training.paid_amount" => 'Paid amount cannot exceed training cost.']);
                }
                if (($training['paid_amount'] ?? 0) > 0 && (empty($training['bank_id']) || empty($training['payment_date']))) {
                    throw ValidationException::withMessages(["applicants.$i.training.bank_id" => 'Training payment requires bank and payment date.']);
                }
            }
            foreach ($row['linked_documents'] ?? [] as $id) {
                $document = ContractFileDocument::findOrFail($id);
                if ((int) $document->passport_holder_id !== (int) $row['passport_holder_id']) {
                    throw ValidationException::withMessages(["applicants.$i.linked_documents" => 'Document belongs to a different passport holder.']);
                }
            }
        }
        $paths = [];
        try {
            $result = DB::transaction(function () use ($request, $data, &$paths, $setup) {
                // Lock the creator to serialize retries and file number generation for this submission.
                User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
                Account::where('code', config('accounts.contract_file_cost'))->lockForUpdate()->firstOrFail();
                $hashData = $data;
                foreach ($request->file('applicants', []) as $i => $uploads) {
                    foreach ($uploads['documents'] ?? [] as $j => $upload) {
                        $hashData['applicants'][$i]['documents'][$j] = ['name' => $upload->getClientOriginalName(), 'sha256' => hash_file('sha256', $upload->getRealPath())];
                    }
                }
                $hash = hash('sha256', json_encode($hashData));
                $existing = DB::table('contract_file_submissions')->where('request_token', $data['request_token'])->first();
                if ($existing) {
                    abort_unless((int) $existing->created_by === Auth::id() && hash_equals($existing->payload_hash, $hash), 409, 'Submission token already used for different data.');

                    return DB::table('contract_files')->where('contract_file_submission_id', $existing->id)->pluck('id')->all();
                }
                $batch = DB::table('contract_file_submissions')->insertGetId(['request_token' => $data['request_token'], 'payload_hash' => $hash, 'company_id' => Auth::user()->company_id ?? 2, 'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
                $ids = [];
                foreach ($data['applicants'] as $i => $row) {
                    // The checklist is the job category's documents plus every Setup requirement matching this
                    // country / visa type / category — resolved here, not taken from the browser, so it is the
                    // same list the card showed (both call ContractFileSetup::resolve()).
                    $received = collect($row['received_documents'] ?? [])->map(fn ($name) => mb_strtolower(trim((string) $name)));
                    $visaTypeId = $setup && ! empty($row['contract_file_visa_type_id']) ? (int) $row['contract_file_visa_type_id'] : null;
                    $docs = collect(ContractFileSetup::resolve((int) $row['country_id'], $visaTypeId, (int) $row['contract_file_category_id'])['documents'])->map(fn ($name) => ['name' => $name, 'checked' => $received->contains(mb_strtolower($name))])->all();
                    $file = ContractFile::create(collect($row)->only(['passport_holder_id', 'client_id', 'client_type', 'country_id', 'contract_file_category_id', 'vendor_id', 'client_price', 'visa_rate', 'submit_date', 'expected_out_date', 'notes', 'cost_paid_amount', 'cost_bank_id', 'purchase_date', 'payable_date'])->all() + ['file_number' => ContractFile::nextFileNumber(), 'contract_file_submission_id' => $batch, 'created_by' => Auth::id(), 'status' => 'file_received', 'cost_paid_amount' => 0, 'required_documents' => $docs] + ($setup ? ['contract_file_visa_type_id' => $visaTypeId] : []));
                    $this->syncPaymentSchedules($file);
                    $this->createClientPosting($file);
                    $this->recordEvent($file, 'stage', $file->status, $file->status_label, 'Created from multi-applicant builder');
                    foreach ($request->file("applicants.$i.documents", []) as $upload) {
                        $path = $upload->store('contract-files/'.$file->id, 'local');
                        $paths[] = $path;
                        ContractFileDocument::create(['contract_file_id' => $file->id, 'passport_holder_id' => $file->passport_holder_id, 'document_type' => 'supporting', 'title' => mb_substr($upload->getClientOriginalName(), 0, 160), 'disk' => 'local', 'path' => $path, 'original_name' => $upload->getClientOriginalName(), 'mime_type' => $upload->getMimeType(), 'size' => $upload->getSize(), 'sha256' => hash_file('sha256', $upload->getRealPath()), 'status' => 'received', 'uploaded_by' => Auth::id()]);
                    }
                    foreach ($row['linked_documents'] ?? [] as $id) {
                        $source = ContractFileDocument::findOrFail($id);
                        ContractFileDocument::create($source->only(['passport_holder_id', 'document_type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256']) + ['contract_file_id' => $file->id, 'linked_document_id' => $source->id, 'status' => 'received', 'uploaded_by' => Auth::id()]);
                    }
                    $training = $row['training'] ?? [];
                    if (! empty($training['enabled'])) {
                        $this->createFileTraining($file, $training);
                    }
                    if (! empty($row['others']['enabled'])) {
                        $this->createFileService($file, $row['others'], 'others', trim($row['others']['title']));
                    }
                    $ids[] = $file->id;
                }
                DB::table('contract_file_submissions')->where('id', $batch)->update(['completed_at' => now(), 'updated_at' => now()]);

                return $ids;
            });
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }

        return response()->json(['success' => true, 'file_ids' => $result, 'message' => 'Files created successfully.']);
    }

    private function createFileTraining(ContractFile $file, array $data): void
    {
        $this->createFileService($file, $data, 'immigration', 'Immigration Training');
    }

    private function createFileService(ContractFile $file, array $data, string $type, string $title): ContractFileServiceCost
    {
        $amount = (float) ($data['amount'] ?? 0);
        $paid = (float) ($data['paid_amount'] ?? 0);
        $bank = ! empty($data['bank_id']) ? Bank::whereKey($data['bank_id'])->lockForUpdate()->firstOrFail() : null;
        if ($paid > 0 && ! $bank?->account_id) {
            throw ValidationException::withMessages(['training' => 'Selected bank has no accounting account.']);
        }
        $service = ContractFileServiceCost::create(['contract_file_id' => $file->id, 'cost_type' => $type, 'title' => $title, 'vendor_id' => $data['vendor_id'], 'amount' => $amount, 'paid_amount' => $paid, 'payment_status' => $paid >= $amount ? 'paid' : ($paid > 0 ? 'partial' : 'due'), 'bank_id' => $bank?->id, 'incurred_on' => $data['incurred_on'] ?? $file->submit_date, 'payment_date' => $data['payment_date'] ?? null, 'due_date' => $data['due_date'] ?? null, 'created_by' => Auth::id()]);
        if ($amount <= 0) {
            return $service;
        }
        if ($paid > 0) {
            Bank::assertCanCover($bank->account_id, $paid, $bank->name);
        }
        User::whereKey($service->vendor_id)->lockForUpdate()->firstOrFail();
        $this->postPartyLedgerRow($service->vendor_id, ['type' => ContractFileServiceCost::SOURCE, 'invoice_id' => $service->id, 'payment_date' => $service->incurred_on, 'reference_no' => $file->file_number, 'debit' => $amount, 'credit' => 0, 'remarks' => 'File '.$title.' — '.$file->file_number]);
        if ($paid > 0) {
            $this->postPartyLedgerRow($service->vendor_id, ['type' => ContractFileServiceCost::SOURCE, 'invoice_id' => $service->id, 'account_id' => $bank->id, 'payment_date' => $service->payment_date, 'reference_no' => $file->file_number, 'debit' => 0, 'credit' => $paid, 'remarks' => 'File '.$title.' payment — '.$file->file_number]);
            $bank->decrement('balance', $paid);
        }
        $this->createPurchaseJournal(ContractFileServiceCost::SOURCE, $service->id, Auth::user()->company_id ?? 2, $service->incurred_on, $file->file_number, 'File '.$title.' — '.$file->file_number, $amount, 0, $amount, null, config('accounts.contract_file_cost'), 'vendor', $service->vendor_id);
        if ($paid > 0) {
            $payment = JournalEntry::create(['company_id' => Auth::user()->company_id ?? 2, 'created_by' => Auth::id(), 'date' => $service->payment_date, 'reference' => $file->file_number, 'source' => ContractFileServiceCost::SOURCE, 'source_id' => $service->id, 'description' => 'File '.$title.' payment — '.$file->file_number, 'beneficiary_party_id' => $service->vendor_id, 'beneficiary' => $service->vendor?->name]);
            JournalItem::create(['journal_entry_id' => $payment->id, 'account_id' => Account::where('code', config('accounts.accounts_payable'))->firstOrFail()->id, 'debit' => $paid, 'credit' => 0, 'party_type' => 'vendor', 'party_id' => $service->vendor_id]);
            JournalItem::create(['journal_entry_id' => $payment->id, 'account_id' => $bank->account_id, 'debit' => 0, 'credit' => $paid]);
        }

        return $service;
    }
}
