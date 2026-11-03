<?php

namespace Modules\WoodArt\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\WoodArt\Modules\Accounts\Support\ExpensePoster;
use App\Services\CompanyPartyDirectory;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;

/**
 * woodart:seed-munshi-villa — loads the Munshi Villa site history that today
 * lives in the owner's spreadsheet ("Munshi Villa New Accounts.xlsx"), so the
 * accountant can sit down with it in the system.
 *
 * MUNSHI VILLA IS A CONSTRUCTION PROJECT: everything goes to EPAL CONSTRUCTIONS
 * (company 3), whose "Munshi Villa Project" department already holds the entries
 * made since 22 Aug. (An earlier version wrongly filed it under Wood Art
 * Interiors; woodart:undo-munshi-wrong-company cleans that up.)
 *
 *     php artisan woodart:seed-munshi-villa --dry-run   # does EVERYTHING, then rolls it back
 *     php artisan woodart:seed-munshi-villa             # the real run
 *
 *     --cutoff=YYYY-MM-DD   import only rows dated BEFORE this day
 *                           (default: the day of the first entry already in the system)
 *     --approve             post the journals now. Without it the entries wait as PENDING.
 *
 * ─── WHAT IT WRITES ──────────────────────────────────────────────────────────
 *  1. COSTS — the sheet's "Over all Accounts" rows dated before the cut-off, as
 *     expenses of company 3 under "Munshi Villa Project", filed through the Expense
 *     desk's own store() (ExpensePoster). They are PENDING: no journal, nothing in
 *     the books, until the accountant opens each one, checks who paid and from
 *     which account, and presses Approve (the Expense desk's own confirm button).
 *  2. STATEMENT — the contract value, the client's four installments, and the
 *     supplier balances the sheet notes "up to 10.09.2026". Project Accounts'
 *     statement tables; NOT in the books until each row is posted.
 *  3. A REVIEW FILE — rows dated on/after the cut-off overlap what is already in
 *     the system. They are NOT imported: they are listed in
 *     storage/app/munshi-villa-overlap-review.csv with the existing entry each one
 *     probably duplicates, for the owner to say which are really new.
 *
 * ─── WHO PAID ────────────────────────────────────────────────────────────────
 * The sheet's REF. NAME says whose hand the money went through. Each cost is
 * filed as that person's own money — what the company owes them until it pays
 * them back. The accountant may change the source before approving. The text is
 * mapped by its FIRST-named person:
 *     NAYEEM -> Nayeemur Rahman        EMAN VAI -> Eman Hossain
 *     RONY VAI -> Md Habibur Rahman    AZIZUL * -> Azizul Haque
 *     BOSS / MOHSIN BOSS -> Md Mohsin
 *     TALIM / HOSSEN (not users) -> Md Mohsin, with "via Talim" in the line
 * The original REF. NAME is kept in every entry's description.
 *
 * ─── SAFETY ──────────────────────────────────────────────────────────────────
 *  · ONE transaction: any refused row rolls the whole run back, nothing half-in.
 *  · Re-runnable: an entry is identified by its reference (WA-MV-001…), and one
 *    that already exists is skipped, never filed twice.
 *  · Run --dry-run first: it runs the real code and then undoes it.
 *  · Pending entries touch no ledger, so a wrong one is simply corrected or
 *    removed before approval; an approved one is reversed, never deleted.
 *
 * Needs the project tables first:
 *     php artisan migrate --path=Modules/WoodArt/Modules/Accounts/Database/Migrations
 */
class SeedMunshiVilla extends Command
{
    protected $signature = 'woodart:seed-munshi-villa {--dry-run : run everything, then roll it all back} {--cutoff= : import only rows dated before YYYY-MM-DD} {--approve : post the journals now instead of leaving the entries pending}';

    protected $description = 'Load the Munshi Villa site history into Epal Constructions (pending, for the accountant to confirm)';

    private const COMPANY = 3;
    private const PROJECT = 'Munshi Villa Project';

    /** The sheet's REF. NAME -> the user it is booked to (exact names, checked at run time). */
    private const PEOPLE = [
        'boss'   => 'Md Mohsin',
        'rony'   => 'Md Habibur Rahman',
        'eman'   => 'Eman Hossain',
        'nayeem' => 'Nayeemur Rahman',
        'azizul' => 'Azizul Haque',
    ];

    public function handle(): int
    {
        $dry     = (bool) $this->option('dry-run');
        $approve = (bool) $this->option('approve');

        ProjectContext::force(self::COMPANY);

        try {
            return $this->seed($dry, $approve);
        } finally {
            ProjectContext::force(null);
        }
    }

    private function seed(bool $dry, bool $approve): int
    {
        $rows = require dirname(__DIR__) . '/Database/Seeders/data/munshi-villa.php';

        $admin = User::role('super admin')->orderBy('id')->first();
        if (! $admin) {
            $this->error('No user with the "super admin" role was found — it is needed to file the expenses.');

            return self::FAILURE;
        }
        Auth::login($admin);

        if (! Schema::hasTable('project_statement') || ! Schema::hasTable('project_terms')) {
            $this->error('The project tables do not exist yet. Run first:');
            $this->error('  php artisan migrate --path=Modules/WoodArt/Modules/Accounts/Database/Migrations');

            return self::FAILURE;
        }

        // ── the people the sheet names, resolved by exact name; ambiguity is an error ──
        $people = [];
        foreach (self::PEOPLE as $key => $name) {
            $found = User::where('name', $name)->get(['id', 'name']);
            if ($found->count() !== 1) {
                $this->error("Expected exactly one user named \"{$name}\" for '{$key}', found {$found->count()}. Nothing was written.");

                return self::FAILURE;
            }
            $people[$key] = $found->first();
        }

        $stray = DB::table('expenses')->where('company_id', 6)->where('reference', 'like', 'WA-MV-%')->whereNull('deleted_at')->count();
        if ($stray > 0) {
            $this->warn("Note: {$stray} Munshi Villa entries sit under Wood Art Interiors (company 6) from the earlier mistaken run. Clean them with:");
            $this->warn('  php artisan woodart:undo-munshi-wrong-company');
        }

        $this->info(($dry ? '[DRY RUN — everything below is rolled back] ' : '') . 'Munshi Villa → EPAL CONSTRUCTIONS (company ' . self::COMPANY . ')');

        DB::beginTransaction();
        try {
            // Project + cost heads (idempotent; creates only what is missing).
            $this->callSilent('woodart:seed-cost-heads', ['--company' => self::COMPANY]);

            $dept = DB::table('expense_departments')
                ->where('company_id', self::COMPANY)->where('name', self::PROJECT)->whereNull('deleted_at')->value('id');
            if (! $dept) {
                throw new \RuntimeException('The "' . self::PROJECT . '" department of company ' . self::COMPANY . ' was not found.');
            }

            $category = ProjectContext::categoryId();
            $heads = DB::table('expense_sub_categories')
                ->where('company_id', self::COMPANY)->where('expense_category_id', $category)->whereNull('deleted_at')
                ->get(['id', 'name', 'expense_category_id'])->keyBy('name');

            // ── the cut-off: what the system already holds is not entered twice ──
            $existing = DB::table('expenses')
                ->where('company_id', self::COMPANY)->where('expense_department_id', $dept)->whereNull('deleted_at')
                // NULL reference counts: `not like` alone silently drops those rows.
                ->where(fn ($q) => $q->whereNull('reference')->orWhere('reference', 'not like', 'WA-MV-%'))
                ->get(['id', 'expense_date', 'title', 'amount']);

            $cutoff = $this->option('cutoff') ?: ($existing->min('expense_date') ? substr((string) $existing->min('expense_date'), 0, 10) : null);

            $import = $cutoff ? array_values(array_filter($rows, fn ($r) => $r['date'] < $cutoff)) : $rows;
            $review = $cutoff ? array_values(array_filter($rows, fn ($r) => $r['date'] >= $cutoff)) : [];

            $this->line('');
            $this->line('  already in the system : ' . $existing->count() . ' entries, ৳ ' . number_format($existing->sum('amount'))
                . ($cutoff ? "  (first dated {$cutoff})" : ''));
            $this->line('  sheet rows BEFORE the cut-off : ' . count($import) . ' rows, ৳ ' . number_format(array_sum(array_column($import, 'amount'))) . '  → will be filed');
            $this->line('  sheet rows ON/AFTER it        : ' . count($review) . ' rows, ৳ ' . number_format(array_sum(array_column($review, 'amount'))) . '  → NOT filed, listed for review');

            // A row filed by an earlier run that now falls on/after the cut-off is no longer
            // wanted. If it is still PENDING (no journal, nobody has touched it) it is hidden
            // (soft-deleted) and appears in the review file instead; an approved or edited one is left alone.
            $retired = 0;
            if ($cutoff) {
                $retired = DB::table('expenses')->where('company_id', self::COMPANY)->where('expense_department_id', $dept)
                    ->where('reference', 'like', 'WA-MV-%')->whereNull('deleted_at')
                    ->where('approval_status', 'pending')->where('expense_date', '>=', $cutoff)
                    ->update(['deleted_at' => now()]);
                if ($retired) {
                    $this->line("  retired : {$retired} earlier-filed pending row(s) that fall inside the overlap zone (hidden, now in the review file)");
                }
            }

            // What the accountant has already confirmed is theirs; this run must add none of its own.
            $approvedBefore = DB::table('expenses')->where('company_id', self::COMPANY)->where('reference', 'like', 'WA-MV-%')
                ->whereNull('deleted_at')->where('approval_status', 'approved')->count();

            $made = 0; $skipped = 0; $total = 0.0;
            $byPerson = []; $byHead = [];

            foreach ($import as $r) {
                if (DB::table('expenses')->where('company_id', self::COMPANY)->where('reference', $r['ref'])->exists()) {
                    $skipped++;
                    continue;
                }

                $head = $heads->get($r['head']);
                if (! $head) {
                    throw new \RuntimeException("{$r['ref']}: cost head \"{$r['head']}\" does not exist for company " . self::COMPANY . '.');
                }
                if (! $r['date']) {
                    throw new \RuntimeException("{$r['ref']}: no date.");
                }

                [$who, $via] = $this->payer($r['who'], $people);

                $desc = $r['desc'];
                $desc .= ' — Excel: ' . $r['who'] . ($via ? " ({$via})" : '');
                if ($r['note']) {
                    $desc .= ' [date: ' . $r['note'] . ']';
                }

                try {
                    ExpensePoster::post([
                        'department_id' => $dept,
                        'category_id'   => $head->expense_category_id,
                        'head_id'       => $head->id,
                        'date'          => $r['date'],
                        'person_id'     => $who->id,
                        'person_name'   => $who->name,
                        'description'   => mb_substr($desc, 0, 250),
                        'amount'        => $r['amount'],
                        'reference'     => $r['ref'],
                    ], 'super-admin', $approve);
                } catch (\Throwable $e) {
                    throw new \RuntimeException("{$r['ref']} (sheet row {$r['src']}, {$r['amount']}): " . $e->getMessage(), 0, $e);
                }

                $made++;
                $total += $r['amount'];
                $byPerson[$who->name] = ($byPerson[$who->name] ?? 0) + $r['amount'];
                $byHead[$r['head']]   = ($byHead[$r['head']] ?? 0) + $r['amount'];
            }

            $this->line('');
            $this->line("  filed : {$made}   skipped (already there): {$skipped}   total ৳ " . number_format($total)
                . ($approve ? '   (APPROVED — journals posted)' : '   (PENDING — no journal yet)'));

            // ── control totals: what is in the Expense desk must equal what was meant to go in ──
            if ($skipped === 0) {
                $expected = array_sum(array_column($import, 'amount'));
                $filed    = (float) DB::table('expenses')->where('company_id', self::COMPANY)->where('reference', 'like', 'WA-MV-%')
                    ->whereNull('deleted_at')->sum('amount');
                $this->line('  control : sheet ৳ ' . number_format($expected) . '  vs  expenses ৳ ' . number_format($filed) . ($expected == $filed ? '   OK' : '   MISMATCH'));
                if ($expected != $filed) {
                    throw new \RuntimeException('The filed total does not equal the sheet total — rolled back.');
                }
            }

            if (! $approve) {
                $approvedAfter = DB::table('expenses')->where('company_id', self::COMPANY)->where('reference', 'like', 'WA-MV-%')
                    ->whereNull('deleted_at')->where('approval_status', 'approved')->count();
                $this->line('  confirmed by this run : ' . ($approvedAfter - $approvedBefore) . "  (must be 0 — they are pending; {$approvedBefore} already confirmed by the accountant are left as they are)");
                if ($approvedAfter !== $approvedBefore) {
                    throw new \RuntimeException('Pending entries must not be confirmed by the seeder — rolled back.');
                }
            }
            arsort($byHead);
            $this->line('');
            foreach ($byHead as $h => $t) {
                $this->line(sprintf('    %-26s %12s', $h, number_format($t)));
            }
            $this->line('');
            $this->line('  filed in the name of (the company would owe them once approved):');
            arsort($byPerson);
            foreach ($byPerson as $p => $t) {
                $this->line(sprintf('    %-26s %12s', $p, number_format($t)));
            }

            $this->writeReview($review, $existing);
            $this->seedStatement($dept);

            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nothing was written. ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->info($dry
            ? 'Dry run finished — everything above was undone (the review file, if any, was still written). Run again without --dry-run to keep it.'
            : 'Done. Open Epal Constructions → Project Accounts → Project Cost → Munshi Villa Project. The ' . ($approve ? 'entries are posted.' : 'entries wait as PENDING for the accountant to confirm.'));

        return self::SUCCESS;
    }

    /** The overlap rows, each with the entry already in the system it probably duplicates. */
    private function writeReview(array $review, $existing): void
    {
        if (! $review) {
            return;
        }

        $path = storage_path('app/munshi-villa-overlap-review.csv');
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");   // BOM so Excel reads the Bengali-free UTF-8 text correctly
        fputcsv($fh, ['sheet ref', 'date', 'REF. NAME', 'purpose (sheet)', 'head', 'amount', 'verdict', 'existing entry (id · date · amount · title)']);

        $pool = $existing->all();
        $likely = 0;

        foreach ($review as $r) {
            $hit = null;
            foreach ($pool as $i => $e) {
                $days = abs((strtotime($r['date']) - strtotime(substr((string) $e->expense_date, 0, 10))) / 86400);
                if ((int) round($e->amount) === (int) $r['amount'] && $days <= 5) {
                    $hit = $e;
                    unset($pool[$i]);
                    break;
                }
            }
            if ($hit) {
                $likely++;
            }

            fputcsv($fh, [
                $r['ref'], $r['date'], $r['who'], $r['desc'], $r['head'], $r['amount'],
                $hit ? 'LIKELY ALREADY ENTERED' : 'no equal amount in the system — probably NEW',
                $hit ? "#{$hit->id} · " . substr((string) $hit->expense_date, 0, 10) . ' · ' . number_format($hit->amount) . ' · ' . $hit->title : '',
            ]);
        }
        fclose($fh);

        $this->line('');
        $this->line('  review file : ' . $path);
        $this->line("                {$likely} of " . count($review) . ' rows look already entered (same amount within 5 days); the rest look new.');
    }

    /** First-named person wins; people who are not users are booked to the Boss with a "via" note. */
    private function payer(string $text, array $people): array
    {
        $t = strtoupper(trim($text));

        $via = null;
        if (str_contains($t, 'TALIM'))  { $via = 'via Talim'; }
        if (str_contains($t, 'HOSSEN')) { $via = 'via Hossen'; }

        $first = 'boss';
        if (preg_match('/^(BOSS|MOHSIN)/', $t))      { $first = 'boss'; }
        elseif (str_starts_with($t, 'RONY'))         { $first = 'rony'; }
        elseif (str_starts_with($t, 'EMAN'))         { $first = 'eman'; }
        elseif (str_starts_with($t, 'NAYEEM'))       { $first = 'nayeem'; }
        elseif (str_starts_with($t, 'AZIZUL'))       { $first = 'azizul'; }
        elseif (! str_contains($t, 'TALIM') && ! str_contains($t, 'HOSSEN')) {
            throw new \RuntimeException("Unrecognised REF. NAME \"{$text}\" — add it to the name map before running.");
        }

        return [$people[$first], $via];
    }

    /** Contract value, the client's installments and the suppliers' balances — statement only, never journals. */
    private function seedStatement(int $dept): void
    {
        $this->line('');
        $now = now();

        DB::table('project_terms')->updateOrInsert(
            ['expense_department_id' => $dept],
            ['contract_value' => 7000000, 'note' => 'Project Value as typed in Munshi Villa New Accounts.xlsx', 'created_at' => $now, 'updated_at' => $now]
        );

        $statement = [
            // the client's installments (sheet rows 136-140; the 4th is typed "3th Payment")
            ['client_installment', null, '1st payment received', '2026-03-12', 1000000, null,                                   'MV-INST-1'],
            ['client_installment', null, '2nd payment received', '2026-04-01', 2000000, null,                                   'MV-INST-2'],
            ['client_installment', null, '3rd payment received', '2026-05-11', 1000000, null,                                   'MV-INST-3'],
            ['client_installment', null, 'Payment received',     '2026-09-21', 1000000, 'typed "3th Payment Received" in the sheet', 'MV-INST-4'],
            // supplier balances noted "Up to 10.09.2026" ("Note-Due" on the Over All Cost Summary tab)
            ['supplier_bill', 'Asia Bricks',        'Bricks purpose due',  '2026-09-10', 160000, 'balance noted in the sheet', 'MV-DUE-1'],
            ['supplier_bill', 'Helal Enterprise',   'Sand purpose due',    '2026-09-10', 66680,  'balance noted in the sheet', 'MV-DUE-2'],
            ['supplier_bill', 'Haji Enterprise',    'Rod due',             '2026-09-10', 231900, 'balance noted in the sheet', 'MV-DUE-3'],
            ['supplier_bill', 'Mostofa (Talim wood)', 'Wood due',          '2026-09-10', 10000,  'balance noted in the sheet', 'MV-DUE-4'],
            ['supplier_bill', 'Contractor Hossen',  'Contractor due',      '2026-09-10', 45000,  'balance noted in the sheet', 'MV-DUE-5'],
        ];

        // The vendors the sheet names (dues AND the suppliers its purchases were paid to), as the
        // company's own register entries, so each has a statement of its own.
        if (Schema::hasTable('company_party')) {
            foreach (['Asia Bricks', 'Haji Enterprise', 'Helal Enterprise', 'Scan Cement', 'Younus Mia (contractor)', 'Contractor Hossen', 'Mostofa (Talim wood)'] as $vendor) {
                CompanyPartyDirectory::resolve($vendor, 'vendor', self::COMPANY);
            }
        }

        $added = 0;
        foreach ($statement as [$kind, $party, $label, $date, $amount, $note, $key]) {
            if (DB::table('project_statement')->where('expense_department_id', $dept)->where('ext_key', $key)->exists()) {
                continue;
            }
            $vendor = ($party && Schema::hasTable('company_party')) ? CompanyPartyDirectory::resolve($party, 'vendor', self::COMPANY) : null;
            DB::table('project_statement')->insert([
                'expense_department_id' => $dept, 'kind' => $kind, 'party' => $vendor->name ?? $party, 'party_id' => $vendor->id ?? null, 'label' => $label,
                'entry_date' => $date, 'amount' => $amount, 'note' => $note, 'ext_key' => $key,
                'created_by' => Auth::id(), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $added++;
        }

        // Rows left by an earlier run (typed names, no vendor yet) get their vendor now.
        $this->line("  statement : contract value ৳ 70,00,000; {$added} new rows (installments ৳ 50,00,000 received; supplier dues ৳ 5,13,580) — not in the books");
    }
}
