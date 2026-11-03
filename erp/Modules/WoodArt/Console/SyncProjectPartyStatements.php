<?php

namespace Modules\WoodArt\Console;

use App\Models\Transaction;
use App\Services\CompanyPartyDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncProjectPartyStatements extends Command
{
    protected $signature = 'project-accounts:sync-party-statements
        {--company=3 : Company id}
        {--project= : Exact project/expense-department name; omit for every project in the company}
        {--client= : Client name to create/attach when the selected project has no client}
        {--dry-run : Run fully and roll back}';

    protected $description = 'Mirror project supplier dues and client contract/installments into company/project Party Statements';

    public function handle(): int
    {
        if (! Schema::hasTable('project_party') || ! Schema::hasColumn('transactions', 'expense_department_id')) {
            $this->error('Project party ledger schema is missing. Run migration 2026_10_07_020000 first.');
            return self::FAILURE;
        }

        $companyId = (int) $this->option('company');
        $projects = DB::table('expense_departments')->where('company_id', $companyId)->whereNull('deleted_at')
            ->when($this->option('project'), fn ($q, $name) => $q->where('name', $name))
            ->orderBy('name')->get(['id', 'name']);
        if ($projects->isEmpty()) {
            $this->error('No matching project was found.');
            return self::FAILURE;
        }
        if ($this->option('client') && $projects->count() !== 1) {
            $this->error('--client may only be used when --project selects exactly one project.');
            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            $created = $updated = $skipped = 0;
            foreach ($projects as $project) {
                [$c, $u, $s] = $this->syncProject($companyId, (int) $project->id, $project->name);
                $created += $c; $updated += $u; $skipped += $s;
            }

            $this->table(['Projects', 'Transactions created', 'Transactions refreshed', 'Rows skipped'], [[
                $projects->count(), $created, $updated, $skipped,
            ]]);

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->warn('DRY RUN: everything was rolled back.');
            } else {
                DB::commit();
                $this->info('Party statements synchronized successfully. Re-running this command will not duplicate rows.');
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function syncProject(int $companyId, int $projectId, string $projectName): array
    {
        $terms = DB::table('project_terms')->where('expense_department_id', $projectId)->first();
        $clientId = (int) ($terms?->client_party_id ?? 0);
        if (! $clientId && $this->option('client')) {
            $client = CompanyPartyDirectory::resolve((string) $this->option('client'), 'client', $companyId, [], $projectId);
            $clientId = (int) $client->id;
            DB::table('project_terms')->updateOrInsert(['expense_department_id' => $projectId], [
                'client_party_id' => $clientId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $terms = DB::table('project_terms')->where('expense_department_id', $projectId)->first();
        }

        // Contract value is the client's opening charge. Materialise it as a
        // statement row so client and vendor history use one source shape.
        if ($clientId && (float) ($terms?->contract_value ?? 0) > 0
            && ! DB::table('project_statement')->where('expense_department_id', $projectId)->where('kind', 'client_bill')->whereNull('deleted_at')->exists()) {
            $firstReceipt = DB::table('project_statement')->where('expense_department_id', $projectId)
                ->where('kind', 'client_installment')->whereNull('deleted_at')->min('entry_date');
            $contractDate = $firstReceipt ? \Carbon\Carbon::parse($firstReceipt)->subDay()->toDateString() : now()->toDateString();
            DB::table('project_statement')->insert([
                'expense_department_id' => $projectId, 'kind' => 'client_bill', 'party_id' => $clientId,
                'party' => null, 'label' => 'Contract value', 'entry_date' => $contractDate,
                'amount' => $terms->contract_value, 'note' => 'Created by project party statement sync',
                'ext_key' => 'PARTY-CONTRACT-' . $projectId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $created = $updated = $skipped = 0;
        $affectedParties = [];
        $rows = DB::table('project_statement')->where('expense_department_id', $projectId)->whereNull('deleted_at')
            ->whereIn('kind', ['client_bill', 'client_installment', 'supplier_bill', 'supplier_payment'])
            ->orderBy('entry_date')->orderBy('id')->get();

        foreach ($rows as $row) {
            $isClient = str_starts_with($row->kind, 'client_');
            $partyId = $isClient ? $clientId : (int) $row->party_id;
            if (! $partyId) { $skipped++; continue; }
            $affectedParties[$partyId] = true;

            $type = $isClient ? 'client' : 'vendor';
            DB::table('company_party')->updateOrInsert(
                ['company_id' => $companyId, 'user_id' => $partyId, 'party_type' => $type],
                ['created_at' => now(), 'updated_at' => now()]
            );
            DB::table('project_party')->updateOrInsert(
                ['expense_department_id' => $projectId, 'user_id' => $partyId, 'party_type' => $type],
                ['created_at' => now(), 'updated_at' => now()]
            );

            $debit = in_array($row->kind, ['client_bill', 'supplier_bill'], true) ? (float) $row->amount : 0.0;
            $credit = $debit > 0 ? 0.0 : (float) $row->amount;
            $existing = Transaction::where('company_id', $companyId)->where('expense_department_id', $projectId)
                ->where('user_id', $partyId)->where('type', 'project_statement')->where('invoice_id', $row->id)->first();

            $values = [
                'company_id' => $companyId, 'expense_department_id' => $projectId, 'user_id' => $partyId,
                'user_type' => $isClient ? 'customer' : 'supplier', 'type' => 'project_statement',
                'invoice_id' => $row->id, 'payment_date' => $row->entry_date ?: now()->toDateString(),
                'reference_no' => 'PRJ-' . $row->id, 'debit' => $debit, 'credit' => $credit,
                'old_balance' => 0, 'balance' => $debit - $credit,
                'remarks' => ($row->label ?: str_replace('_', ' ', $row->kind)) . ' — ' . $projectName,
            ];
            $existing ? $existing->update($values) : Transaction::create($values);
            $existing ? $updated++ : $created++;
        }

        // Stored balance is a convenience snapshot used by older list pages.
        // Re-chain every affected party after inserting historical rows; the
        // statement screen also independently derives balances from Dr/Cr.
        foreach (array_keys($affectedParties) as $partyId) {
            $running = 0.0;
            foreach (Transaction::where('company_id', $companyId)->where('user_id', $partyId)->orderBy('id')->lockForUpdate()->get() as $transaction) {
                $old = $running;
                $running += (float) $transaction->debit - (float) $transaction->credit;
                DB::table('transactions')->where('id', $transaction->id)->update(['old_balance' => $old, 'balance' => $running]);
            }
        }

        if (! $clientId && $rows->whereIn('kind', ['client_bill', 'client_installment'])->isNotEmpty()) {
            $this->warn("{$projectName}: client rows skipped because no client is assigned. Re-run with --project=\"{$projectName}\" --client=\"Client Name\".");
        }
        return [$created, $updated, $skipped];
    }
}
