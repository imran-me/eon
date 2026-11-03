<?php

namespace Modules\WoodArt\Console;

use App\Models\Transaction;
use App\Models\User;
use App\Services\CompanyPartyDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;

/**
 * Munshi Villa — bring Epal Constructions in line with the owner's UPDATED workbook
 * ("MUNSHI BILLAH (DUPLEX).xlsx"). Run it after woodart:seed-munshi-villa.
 *
 *  1. SUPPLIER LEDGERS — every bill and payment on the Haji, Helal, Asia Bricks and Labour
 *     (Talim) sheets becomes a project_statement row, so each supplier's Party Statement shows
 *     the whole history and ends on the figure the sheet ends on. Statement only: nothing is
 *     posted to the books (no journal), exactly like the rows the seeder filed.
 *  2. The five single-figure dues the first seeder filed ("up to 10.09") are replaced by those
 *     ledgers (Asia, Helal, Haji) or are not in the updated file at all (Hossen, Mostofa). They
 *     are retired — marked removed, never deleted — and where one had already reached a party's
 *     statement an opposite row is ADDED so that statement nets to zero.
 *  3. The one cost entry the updated file no longer has (WA-MV-019, ৳2,000 daily allowance,
 *     21.04) is retired if it is still pending. One the accountant has confirmed is left alone
 *     and reported: it is in the books, so it is reversed from the Expense desk, not here.
 *  4. The statement rows are mirrored into the company's Party Statement by the project-accounts
 *     sync, so the vendor pages show them.
 *
 * Nothing is deleted. Idempotent: every imported row has a key, and a second run adds nothing.
 * Run --dry-run first: it runs the real code and then undoes it.
 *
 * Contractors (Younus, Hossen): their sheets list transfers only, no bills, and each transfer is
 * already a Contractor cost entry (same ৳7,16,000). So every transfer goes in as the work paid for at
 * that moment — a bill and its payment of the same amount — which shows the whole history on the
 * contractor's statement and leaves his balance at 0 instead of a phantom advance.
 */
class UpdateMunshiVilla extends Command
{
    protected $signature = 'woodart:update-munshi-villa {--dry-run : run everything, then roll it all back}';

    protected $description = 'Post the updated Munshi Villa workbook: supplier ledgers into the Party Statement, retire superseded rows';

    private const COMPANY = 3;
    private const PROJECT = 'Munshi Villa Project';

    /** Single-figure dues filed by the first seeder, and why each goes. */
    private const SUPERSEDED = [
        'MV-DUE-1' => 'replaced by the Asia Bricks ledger',
        'MV-DUE-2' => 'replaced by the Helal Enterprise ledger',
        'MV-DUE-3' => 'replaced by the Haji Enterprise ledger',
        'MV-DUE-4' => 'Mostofa (Talim wood) is not in the updated workbook',
        'MV-DUE-5' => 'Contractor Hossen shows no due in the updated workbook',
    ];

    /** The cost entry the updated workbook no longer has. */
    private const DROPPED_COST = 'WA-MV-019';

    public function handle(): int
    {
        ProjectContext::force(self::COMPANY);

        try {
            return $this->run_((bool) $this->option('dry-run'));
        } finally {
            ProjectContext::force(null);
        }
    }

    private function run_(bool $dry): int
    {
        $admin = User::role('super admin')->orderBy('id')->first();
        if (! $admin) {
            $this->error('No user with the "super admin" role was found.');

            return self::FAILURE;
        }
        Auth::login($admin);

        foreach (['project_statement', 'project_party', 'company_party'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->error("Table {$t} does not exist yet. Run the pending migrations for the party ledgers first (see the deploy notes).");

                return self::FAILURE;
            }
        }
        if (! Schema::hasColumn('transactions', 'expense_department_id')) {
            $this->error('transactions.expense_department_id is missing — run migration 2026_10_07_020000_create_project_party_table first.');

            return self::FAILURE;
        }

        $dept = DB::table('expense_departments')->where('company_id', self::COMPANY)->where('name', self::PROJECT)->whereNull('deleted_at')->first();
        if (! $dept) {
            $this->error('"' . self::PROJECT . '" does not exist under Epal Constructions. Run woodart:seed-munshi-villa first.');

            return self::FAILURE;
        }

        $ledgers = require dirname(__DIR__) . '/Database/Seeders/data/munshi-villa-ledgers.php';

        $this->info(($dry ? '[DRY RUN — everything below is rolled back] ' : '') . 'Munshi Villa — updated workbook → EPAL CONSTRUCTIONS (company ' . self::COMPANY . ')');

        DB::beginTransaction();
        try {
            $this->retireSuperseded((int) $dept->id);
            $this->retireDroppedCost((int) $dept->id);
            $this->importLedgers((int) $dept->id, $ledgers);

            // Mirror the statement rows into the company's Party Statement (the same job the
            // project-accounts sync does; idempotent).
            $this->callSilent('project-accounts:sync-party-statements', ['--company' => self::COMPANY, '--project' => $dept->name]);

            $ok = $this->control((int) $dept->id, $ledgers);
            $this->summary((int) $dept->id);

            if (! $ok) {
                throw new \RuntimeException('A supplier does not end on the sheet\'s figure — everything was rolled back.');
            }

            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->{$dry ? 'warn' : 'info'}($dry
            ? 'Dry run finished — everything above was undone. Run again without --dry-run to keep it.'
            : 'Done. Open Epal Constructions → Vendors / Party Statement, filter Munshi Villa Project. Nothing was posted to the books.');

        return self::SUCCESS;
    }

    /* ── 2. superseded dues ───────────────────────────────────────────────── */

    private function retireSuperseded(int $dept): void
    {
        $this->line('');
        $retired = 0;

        foreach (self::SUPERSEDED as $key => $why) {
            $row = DB::table('project_statement')->where('expense_department_id', $dept)->where('ext_key', $key)->whereNull('deleted_at')->first();
            if (! $row) {
                continue;
            }
            if ($row->journal_entry_id) {
                $this->warn("  kept     : {$key} {$row->party} ৳ " . number_format($row->amount) . ' is already in the books — reverse it from the project sheet, not here.');
                continue;
            }

            $this->reverseMirror($row);
            DB::table('project_statement')->where('id', $row->id)->update([
                'deleted_at' => now(), 'updated_at' => now(),
                'note' => trim(($row->note ? $row->note . ' · ' : '') . 'removed: ' . $why),
            ]);
            $this->line("  retired  : {$key} {$row->party} ৳ " . number_format($row->amount) . " — {$why}");
            $retired++;
        }

        if (! $retired) {
            $this->line('  superseded dues : nothing to retire (already done).');
        }
    }

    /** A due that already reached the party's statement is cancelled by an added opposite row, never by deleting. */
    private function reverseMirror(object $row): void
    {
        $partyId = (int) ($row->party_id ?? 0);
        $mirror = $partyId ? Transaction::where('company_id', self::COMPANY)->where('user_id', $partyId)
            ->where('type', 'project_statement')->where('invoice_id', $row->id)->first() : null;
        if (! $mirror) {
            return;
        }
        if (Transaction::where('company_id', self::COMPANY)->where('user_id', $partyId)->where('type', 'project_statement_reversal')->where('invoice_id', $row->id)->exists()) {
            return;
        }

        $last = Transaction::where('company_id', self::COMPANY)->where('user_id', $partyId)->lockForUpdate()->latest('id')->first();
        $old = (float) ($last?->balance ?? 0);
        $debit = (float) $mirror->credit;
        $credit = (float) $mirror->debit;

        Transaction::create([
            'company_id' => self::COMPANY, 'expense_department_id' => $row->expense_department_id, 'user_id' => $partyId,
            'user_type' => $mirror->user_type, 'type' => 'project_statement_reversal', 'invoice_id' => $row->id,
            'payment_date' => now()->toDateString(), 'reference_no' => 'REV-PRJ-' . $row->id,
            'old_balance' => $old, 'debit' => $debit, 'credit' => $credit, 'balance' => $old + $debit - $credit,
            'remarks' => 'Reversal — superseded by the updated Munshi Villa workbook',
        ]);
    }

    /* ── 3. the cost entry that is gone ───────────────────────────────────── */

    private function retireDroppedCost(int $dept): void
    {
        $e = DB::table('expenses')->where('company_id', self::COMPANY)->where('expense_department_id', $dept)
            ->where('reference', self::DROPPED_COST)->first();

        if (! $e) {
            $this->line('  ' . self::DROPPED_COST . ' : not on file.');

            return;
        }
        if ($e->deleted_at) {
            $this->line('  ' . self::DROPPED_COST . ' : already retired.');

            return;
        }
        if ($e->approval_status !== 'pending') {
            $this->warn('  kept     : ' . self::DROPPED_COST . ' ৳ ' . number_format($e->amount) . " is {$e->approval_status} (in the books) — reverse it from the Expense desk if it must go.");

            return;
        }

        DB::table('expenses')->where('id', $e->id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        $this->line('  retired  : ' . self::DROPPED_COST . ' ৳ ' . number_format($e->amount) . ' (daily allowance advance, ' . substr((string) $e->expense_date, 0, 10) . ') — not in the updated workbook; it was pending, no journal.');
    }

    /* ── 1. supplier ledgers ──────────────────────────────────────────────── */

    private function importLedgers(int $dept, array $ledgers): void
    {
        $this->line('');
        $now = now();

        foreach ($ledgers as $l) {
            $party = CompanyPartyDirectory::resolve($l['supplier'], 'vendor', self::COMPANY, [], $dept);
            $added = 0;

            foreach ($l['rows'] as $r) {
                if (DB::table('project_statement')->where('expense_department_id', $dept)->where('ext_key', $r['key'])->exists()) {
                    continue;
                }
                DB::table('project_statement')->insert([
                    'expense_department_id' => $dept,
                    'kind'       => $r['kind'] === 'bill' ? 'supplier_bill' : 'supplier_payment',
                    'party'      => $party->name, 'party_id' => $party->id,
                    'label'      => $r['label'] !== '' ? $r['label'] : ($r['kind'] === 'bill' ? 'Purchase' : 'Payment'),
                    'entry_date' => $r['date'], 'amount' => $r['amount'],
                    'note'       => $r['note'] ?: null, 'ext_key' => $r['key'],
                    'created_by' => Auth::id(), 'created_at' => $now, 'updated_at' => $now,
                ]);
                $added++;
            }

            $this->line(sprintf('  %-28s %2d new row(s) of %2d  (sheet "%s")', $party->name, $added, count($l['rows']), $l['sheet']));
        }
    }

    /* ── checks ───────────────────────────────────────────────────────────── */

    /** Each supplier's own ledger must end on the figure its sheet ends on. */
    private function control(int $dept, array $ledgers): bool
    {
        $this->line('');
        $this->line('  CONTROL — what each supplier ends on (bills − payments) vs the sheet');
        $all = true;

        foreach ($ledgers as $l) {
            $party = User::role('vendor')->where('name', $l['supplier'])->first();
            $due = $party ? (float) DB::table('project_statement')->where('expense_department_id', $dept)->where('party_id', $party->id)->whereNull('deleted_at')
                ->selectRaw("COALESCE(SUM(CASE WHEN kind='supplier_bill' THEN amount WHEN kind='supplier_payment' THEN -amount ELSE 0 END),0) due")->value('due') : null;
            $mirror = $party ? (float) Transaction::where('company_id', self::COMPANY)->where('user_id', $party->id)->where('expense_department_id', $dept)
                ->selectRaw('COALESCE(SUM(debit - credit),0) b')->value('b') : null;

            $good = $due !== null && abs($due - (float) $l['closing']) < 0.005 && abs($mirror - (float) $l['closing']) < 0.005;
            $all = $all && $good;
            $this->line(sprintf('    %-28s statement ৳ %9s · party ledger ৳ %9s · sheet ৳ %9s  %s',
                $l['supplier'], number_format($due ?? 0), number_format($mirror ?? 0), number_format($l['closing']), $good ? 'OK' : '**MISMATCH**'));
        }

        return $all;
    }

    private function summary(int $dept): void
    {
        $due = (float) DB::table('project_statement')->where('expense_department_id', $dept)->whereNull('deleted_at')
            ->selectRaw("COALESCE(SUM(CASE WHEN kind='supplier_bill' THEN amount WHEN kind='supplier_payment' THEN -amount ELSE 0 END),0) due")->value('due');
        $contract = (float) DB::table('project_terms')->where('expense_department_id', $dept)->value('contract_value');
        $received = (float) DB::table('project_statement')->where('expense_department_id', $dept)->where('kind', 'client_installment')->whereNull('deleted_at')->sum('amount');
        $booked = (int) DB::table('project_statement')->where('expense_department_id', $dept)->whereNull('deleted_at')->whereNotNull('journal_entry_id')->count();

        $this->line('');
        $this->line('  project : supplier due ৳ ' . number_format($due) . ' (sheets: Asia 1,60,000 + Haji 75,041 + Helal 53,898 + Talim 0 = 2,88,939)');
        $this->line('            client contract ৳ ' . number_format($contract) . ' · received ৳ ' . number_format($received) . ' · still due ৳ ' . number_format($contract - $received));
        $this->line('            statement rows already in the books (this run adds none): ' . $booked);
        $this->line('  note    : the workbook\'s own "DUE BALANCE" cell says ৳ 2,81,898, but its label (Asia 160K + Halal 58,148) adds to ৳ 2,18,148 — the');
        $this->line('            sheet disagrees with itself by ৳ 63,750. These dues come from the supplier sheets, not from that cell.');
    }
}
