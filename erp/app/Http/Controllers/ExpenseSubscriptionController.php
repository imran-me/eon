<?php

namespace App\Http\Controllers;

use App\Models\DmRenewalPayment;
use App\Services\DmApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;

/**
 * Subscriptions & Renewals — DM's register, joined to this ERP's ledger.
 *
 * ── WHERE THE DATA COMES FROM, AND WHY IT IS NOT STORED HERE ──────────────────
 * DM (dm.epal.com.bd) is the register of what the group subscribes to and which
 * documents expire when. It is fetched live through DmApiService and NOT copied
 * into this database, because DmApiService is read-only — two `fetch` methods,
 * no write — so the ERP could never keep a local copy in step with the system
 * that is actually edited. Two registers that disagree are worse than one.
 *
 * What this screen adds is the single thing DM cannot know: which EXPENSE paid a
 * given renewal. That lives in dm_renewal_payments, one row per billing period,
 * and it is the whole point of the page — DM answers "what falls due", the
 * ledger answers "what did we pay", and until they are joined neither can tell
 * you that a renewal was missed or paid twice.
 *
 * ── HONEST GAPS IN THE DM PAYLOAD ─────────────────────────────────────────────
 * The screen was designed before the data source was known, so some columns have
 * no DM equivalent. They are left blank rather than invented:
 *
 *   vendor        — DM has no vendor field. `name` is "Hostinger Hosting", which
 *                   is product and vendor in one string; splitting it would be
 *                   guesswork.
 *   paid_from     — unknowable until the expense exists. Filled from the linked
 *                   expense's settlement source once it does, which makes an
 *                   empty cell mean "not yet paid" — useful, not missing.
 *   owner         — no DM equivalent. Shows whoever filed the expense.
 *   document cost — DM sends no amount for a document renewal at all. The figure
 *                   shown is what was paid LAST cycle, carried forward from
 *                   dm_renewal_payments and labelled as such.
 *   EMI           — DM carries none. Instalments live on the financing desk
 *                   (App\Models\FinancingLoan), which already splits principal
 *                   from interest and posts its own journals. Duplicating them
 *                   here would put the same liability on the balance sheet
 *                   twice, so the EMI filter is hidden until it reads from
 *                   FinancingSchedule.
 *
 * ── CURRENCY ──────────────────────────────────────────────────────────────────
 * DM bills some subscriptions in USD (`amount: "65.00", currency: "USD"`). There
 * is no FX source in this application, so nothing here converts anything. A USD
 * commitment prints in USD, and the BDT run-rate counts BDT rows only and says
 * so on the tile. Inventing a rate would make the headline figure wrong in a way
 * nobody could see.
 *
 * ── IT CANNOT TAKE THE PAGE DOWN ──────────────────────────────────────────────
 * DM is a network call to another server. Every fetch is wrapped, a failure logs
 * and renders an honest banner with an empty table, and failures are never
 * cached — so a DM outage costs this one tab its rows and nothing else. The tab
 * itself is already inside the try/catch that layout/expense-tabs.blade.php
 * applies to every tab.
 */
class ExpenseSubscriptionController implements HasMiddleware
{
    /**
     * Five minutes, matching HeadlineNoticeController::DM_CACHE_TTL so the ticker
     * and this desk cannot show different answers to the same question minutes
     * apart.
     */
    private const CACHE_TTL = 300;

    private const CACHE_KEY = 'expense-subscriptions.dm';

    /**
     * High enough to be the whole register rather than a page of it. A silently
     * truncated list would read as "nothing else is due", which is the one
     * conclusion this screen must never invite.
     */
    private const PER_PAGE = 200;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view expense', only: ['index']),
        ];
    }

    public function index(Request $request)
    {
        // Accounts works the expense desk from the super admin panel, so this is
        // the real access rule rather than a placeholder: the Renewal Center this
        // screen mirrors is a super-admin surface too
        // (HeadlineNoticeController::DM_ROLE). 404 rather than 403 — a screen
        // somebody may not use need not announce itself.
        abort_unless((bool) ($request->user()?->is_super_admin ?? false), 404);

        $dm   = $this->fetchDm();
        $rows = $this->buildRows($dm['accesses'], $dm['documents'], (string) $request->route('role'));

        return view('expense-subscriptions.index', [
            'rows'        => $rows,
            'summary'     => $this->summarise($rows),
            'dmFailed'    => $dm['failed'],
            // Surfaced on screen because it is otherwise invisible: with this on,
            // the desk is reading two JSON files in app/Services/ and every
            // figure is fictional while looking entirely real.
            'dmFileMode'  => (bool) env('DM_USE_FILE_DATA', false),
            'dmPortalUrl' => (string) config('services.dm_portal'),
        ]);
    }

    /* ── DM ──────────────────────────────────────────────────────────────── */

    /**
     * Both DM endpoints, or empty lists and a failure flag.
     *
     * Cache::remember() is deliberately NOT used: it would cache a failure for
     * five minutes, so one blip during a deploy would leave the desk claiming
     * nothing is due long after DM came back. Only a successful fetch is stored.
     *
     * @return array{accesses: array, documents: array, failed: bool}
     */
    private function fetchDm(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $dm = app(DmApiService::class);

            $accesses  = $dm->fetchAccessItems(1, self::PER_PAGE);
            $documents = $dm->fetchExpiredDocuments(1, self::PER_PAGE, ['scope' => 'all']);

            // DmApiService returns null when the URL or token is missing and when
            // the request failed — it does not distinguish. Both endpoints coming
            // back null means we learned nothing, which is not the same as "the
            // register is empty" and must not be displayed as though it were.
            if ($accesses === null && $documents === null) {
                return ['accesses' => [], 'documents' => [], 'failed' => true];
            }

            $result = [
                'accesses'  => is_array($accesses) ? $accesses : [],
                'documents' => is_array($documents) ? $documents : [],
                'failed'    => false,
            ];

            Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

            return $result;
        } catch (\Throwable $e) {
            logger()->warning('Subscriptions desk could not load DM: ' . $e->getMessage());

            return ['accesses' => [], 'documents' => [], 'failed' => true];
        }
    }

    /* ── Rows ────────────────────────────────────────────────────────────── */

    private function buildRows(array $accesses, array $documents, string $role): array
    {
        $rows = [];

        foreach ($accesses as $access) {
            if (is_array($access) && $row = $this->subscriptionRow($access)) {
                $rows[] = $row;
            }
        }

        foreach ($documents as $document) {
            if (is_array($document) && $row = $this->documentRow($document)) {
                $rows[] = $row;
            }
        }

        $rows = $this->attachPayments($rows, $role);

        // Unpaid first and oldest-due first inside that, so what has been missed
        // is at the top of the table and settled history sinks below it.
        usort($rows, fn (array $a, array $b) => [$a['paid'], $a['next_due']] <=> [$b['paid'], $b['next_due']]);

        return $rows;
    }

    /**
     * One DM company-access record — a service billed on a cycle.
     */
    private function subscriptionRow(array $access): ?array
    {
        $id = data_get($access, 'id');

        if (! $id) {
            return null;
        }

        // Which field means "due" is decided in ONE place, shared with the
        // dashboard Renewal Center — if the two disagreed, a renewal would read
        // as paid here and still overdue there.
        $dueDate = DmRenewalPayment::dueDateFor($access, DmRenewalPayment::SUBSCRIPTION);

        if (! $dueDate) {
            return null;
        }

        $due = Carbon::parse($dueDate);

        $amount = (float) (data_get($access, 'amount') ?: 0);

        return $this->finishRow([
            'kind'         => 'subscription',
            'source_type'  => DmRenewalPayment::SUBSCRIPTION,
            // A subscription is bought by the group, never by a person, so it
            // is always company money.
            'scope'        => 'company',
            'dm_id'        => (int) $id,
            // A subscription is one DM row reused each cycle, so the row IS the
            // group — unlike a document, whose next renewal is a new row.
            'dm_group_id'  => (int) $id,
            'name'         => (string) (data_get($access, 'name') ?: 'Untitled subscription'),
            'vendor'       => '',
            'company'      => (string) (data_get($access, 'company.name') ?: data_get($access, 'company_name') ?: ''),
            'category'     => (string) (data_get($access, 'access_type') ?: 'Subscription'),
            'sub_category' => '',
            'amount'       => $amount,
            'amount_known' => $amount > 0,
            'amount_note'  => '',
            'currency'     => $this->normaliseCurrency(data_get($access, 'currency')),
            'cycle'        => $this->normaliseCycle(data_get($access, 'subscription_type')),
            'due'          => $due,
            // A lapsed subscription stops billing; it does not invalidate
            // anything. DM has no criticality flag and guessing one from the due
            // date would only repeat what the due tag already says.
            'critical'     => false,
            'status'       => data_get($access, 'deleted_at') ? 'Closed' : 'Active',
            'link_url'     => $this->externalUrl(data_get($access, 'url')),
        ]);
    }

    /**
     * One DM expired-documents record — a licence, permit or certificate.
     */
    private function documentRow(array $document): ?array
    {
        $id = data_get($document, 'id');

        if (! $id) {
            return null;
        }

        $dueDate = DmRenewalPayment::dueDateFor($document, DmRenewalPayment::DOCUMENT);

        if (! $dueDate) {
            return null;
        }

        $due = Carbon::parse($dueDate);

        $created = $this->parseDate(
            data_get($document, 'created_date') ?: data_get($document, 'documents.created_date')
        );

        return $this->finishRow([
            'kind'         => 'renewal',
            'source_type'  => DmRenewalPayment::DOCUMENT,
            'scope'        => $this->documentScope($document),
            'dm_id'        => (int) $id,
            // document_id is what survives a renewal; see the migration note.
            'dm_group_id'  => (int) (data_get($document, 'document_id') ?: $id),
            'name'         => (string) (
                data_get($document, 'documents.title') ?: data_get($document, 'title') ?: 'Untitled document'
            ),
            'vendor'       => '',
            'company'      => (string) (data_get($document, 'documents.company.name') ?: ''),
            // The type/category relations are not always expanded in the payload,
            // so fall back to the id the same way the Renewal Center does rather
            // than printing nothing.
            'category'     => (string) (
                data_get($document, 'document_type.name')
                    ?: 'Type #' . (data_get($document, 'document_type_id') ?: '—')
            ),
            'sub_category' => (string) (
                data_get($document, 'document_category.name')
                    ?: 'Category #' . (data_get($document, 'document_category_id') ?: '—')
            ),
            // DM carries no cost for a document. attachPayments() fills this from
            // what was paid last cycle when there is a record of it.
            'amount'       => 0.0,
            'amount_known' => false,
            'amount_note'  => '',
            'currency'     => 'BDT',
            'cycle'        => $this->cycleFromSpan($created, $due),
            'due'          => $due,
            // Unlike a subscription, a lapsed licence or permit genuinely stops
            // something — that is what the flag was designed to mark.
            'critical'     => true,
            'status'       => 'Active',
            'link_url'     => $this->externalUrl(
                data_get($document, 'file_path') ?: data_get($document, 'image_path')
            ),
        ]);
    }

    /**
     * Is this document a company cost, or somebody's own paperwork?
     *
     * Decided on DM's CATEGORY — the folder — not its type, because the type
     * lies in both directions: "Identity & Personal" appears under the Epal
     * Travels folder (a company document) and "Business & Legal" appears under
     * FATHER BOSS DOCS (somebody's father's). The folder is the honest signal:
     * DM files company paperwork under a company name and personal paperwork
     * under a person's.
     *
     * Unlisted folders come back personal, and that direction is deliberate. A
     * folder nobody added to config/dm.php hides a due date from this desk,
     * which someone will notice and fix; the opposite default would quietly
     * invite a personal passport onto the company's books, which nobody would.
     */
    private function documentScope(array $document): string
    {
        $folder = strtolower(trim((string) data_get($document, 'document_category.name')));

        if ($folder === '') {
            return 'personal';
        }

        $company = array_map(
            fn ($name) => strtolower(trim((string) $name)),
            (array) config('dm.company_document_categories', [])
        );

        return in_array($folder, $company, true) ? 'company' : 'personal';
    }

    /**
     * Everything derived from the due date, plus the per-month figure that lets a
     * yearly licence and a monthly tool be added together.
     */
    private function finishRow(array $row): array
    {
        $due   = $row['due'];
        $dueIn = (int) round(Carbon::today()->diffInDays($due, false));

        $row['due_in']         = $dueIn;
        $row['next_due']       = $due->toDateString();
        $row['next_due_label'] = $due->format('d M Y');
        $row['monthly_value']  = $this->monthlyValue((float) $row['amount'], $row['cycle']);

        // Closed commitments are not chased and not forecast, so they carry no
        // due state at all — only their dates remain, as history.
        $row['due_state'] = $row['status'] !== 'Active'
            ? 'none'
            : match (true) {
                $dueIn < 0  => 'overdue',
                $dueIn <= 7 => 'soon',
                default     => 'later',
            };

        $row['due_note'] = match ($row['due_state']) {
            'overdue' => abs($dueIn) . ' ' . (abs($dueIn) === 1 ? 'day' : 'days') . ' overdue',
            'soon'    => $dueIn === 0 ? 'Due today' : 'in ' . $dueIn . ' days',
            'later'   => 'in ' . $dueIn . ' days',
            default   => '—',
        };

        unset($row['due']);

        return $row;
    }

    /* ── The join to the ledger ──────────────────────────────────────────── */

    /**
     * Mark each DM row paid or unpaid, and fill in what only the expense knows.
     *
     * One query for every row on screen rather than one per row — the desk is
     * read on every visit and a per-row lookup would be dozens of queries for a
     * page that is mostly unchanged.
     */
    private function attachPayments(array $rows, string $role): array
    {
        if (! $rows) {
            return $rows;
        }

        // Fetched by GROUP, not by dm_id: a document's previous renewal is a
        // different DM row, and finding it is the only way to know what the thing
        // cost last time.
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row['source_type']][] = $row['dm_group_id'];
        }

        $payments = DmRenewalPayment::query()
            ->with(['expense.bank', 'expense.pettyCashFloat', 'expense.reimburseTo', 'recorder'])
            ->where(function ($query) use ($groups) {
                foreach ($groups as $sourceType => $ids) {
                    $query->orWhere(fn ($scope) => $scope
                        ->where('source_type', $sourceType)
                        ->whereIn('dm_group_id', array_values(array_unique($ids))));
                }
            })
            ->orderBy('due_date')
            ->get();

        $byPeriod = [];
        $lastPaid = [];

        foreach ($payments as $payment) {
            $byPeriod[DmRenewalPayment::periodKey(
                $payment->source_type,
                $payment->dm_id,
                $payment->due_date->toDateString()
            )] = $payment;

            // Ordered by due_date, so the last write wins and this ends up as the
            // most recent settled amount for the commitment.
            if ($payment->amount > 0) {
                $lastPaid[$payment->source_type . ':' . $payment->dm_group_id] = $payment;
            }
        }

        return array_map(function (array $row) use ($byPeriod, $lastPaid, $role) {
            $key     = DmRenewalPayment::periodKey($row['source_type'], $row['dm_id'], $row['next_due']);
            $payment = $byPeriod[$key] ?? null;

            $row['paid']       = (bool) $payment;
            $row['expense_id'] = $payment?->expense_id;
            $row['paid_from']  = '';
            $row['owner']      = '';

            if ($payment) {
                // Settled, so the due state is history rather than a warning —
                // an overdue tag on something already paid is just noise.
                $row['due_state'] = 'paid';
                $row['due_note']  = 'Paid ' . ($payment->paid_at?->format('d M Y') ?: '');
                $row['paid_from'] = $this->settlementLabel($payment);
                $row['owner']     = (string) ($payment->recorder?->name ?: '');

                // The amount actually paid outranks the expected figure. For a
                // document renewal it is the only amount that exists at all.
                if ($payment->amount > 0) {
                    $row['amount']      = (float) $payment->amount;
                    $row['currency']    = $this->normaliseCurrency($payment->currency);
                    $row['amount_note'] = 'Paid';
                }
            } elseif (! $row['amount_known']) {
                // Nothing from DM and nothing paid for this period — fall back to
                // what the same commitment cost last cycle, clearly labelled so
                // it is never mistaken for a figure DM confirmed.
                $carry = $lastPaid[$row['source_type'] . ':' . $row['dm_group_id']] ?? null;

                if ($carry) {
                    $row['amount']      = (float) $carry->amount;
                    $row['currency']    = $this->normaliseCurrency($carry->currency);
                    $row['amount_note'] = 'Last paid ' . $carry->due_date->format('M Y');
                }
            }

            $row['monthly_value'] = $this->monthlyValue((float) $row['amount'], $row['cycle']);
            // Reminder-only rows get no way to file an expense. The button is the
            // invitation; removing it is what stops a personal document being
            // booked against the company by a click nobody thought about.
            $row['record_url']    = $row['scope'] === 'company' ? $this->recordUrl($row, $role) : null;
            $row['expense_url']   = $payment && $payment->expense
                ? $this->expenseUrl($payment->expense->title, $role)
                : null;

            return $row;
        }, $rows);
    }

    /**
     * Where the money came from, read off the expense rather than stored twice.
     *
     * Follows the same order of precedence as
     * ExpenseController::settlementAccountId(), so this label can never claim a
     * different source than the journal actually credited.
     */
    private function settlementLabel(DmRenewalPayment $payment): string
    {
        $expense = $payment->expense;

        if (! $expense) {
            return 'Expense removed';
        }

        if ($expense->reimburse_to_user_id) {
            return 'Owed to ' . ($expense->reimburseTo?->name ?: 'a colleague');
        }

        if ($expense->petty_cash_float_id) {
            return $expense->pettyCashFloat?->name
                ? 'Float — ' . $expense->pettyCashFloat->name
                : 'Petty cash float';
        }

        if ($expense->bank_id) {
            return (string) ($expense->bank?->name ?: 'Bank');
        }

        return 'Office Cash';
    }

    /**
     * The expense form, pre-filled from the DM row.
     *
     * Deep-links to the existing expense desk rather than adding a second create
     * form: the classification picker, the settlement-source rules and the
     * approval path all stay in one place, so a renewal is coded and posted
     * exactly like a hand-filed expense.
     */
    private function recordUrl(array $row, string $role): string
    {
        return route('role.expenses.index', array_filter([
            'role'           => $role,
            'dm_source_type' => $row['source_type'],
            'dm_id'          => $row['dm_id'],
            'dm_group_id'    => $row['dm_group_id'],
            'dm_due_date'    => $row['next_due'],
            'dm_title'       => $row['name'],
            // Only ever a BDT figure. Pre-filling a USD 65.00 subscription into a
            // form that records taka would put 65 into the ledger for a bill of
            // roughly eight thousand, and it would look deliberate.
            'dm_amount'      => ($row['currency'] === 'BDT' && $row['amount'] > 0)
                ? number_format($row['amount'], 2, '.', '')
                : null,
            'dm_currency'    => $row['currency'],
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /** The expense list, filtered to the one that settled this renewal. */
    private function expenseUrl(?string $title, string $role): string
    {
        return route('role.expenses.index', array_filter([
            'role'  => $role,
            'title' => $title,
        ]));
    }

    /* ── Totals ──────────────────────────────────────────────────────────── */

    /**
     * Everything above the table, counted off the rows below it.
     *
     * Derived rather than stored: a KPI strip that disagrees with its own table
     * reads as a bug, and this is the arithmetic anyone would do by hand.
     */
    private function summarise(array $rows): array
    {
        // Personal paperwork is tracked for the reminder and nothing else, so it
        // is absent from every figure above the table — counting a director's
        // passport as an overdue company obligation would make the one number
        // people act on wrong.
        $company = array_filter($rows, fn ($row) => $row['scope'] === 'company');

        $active = array_filter($company, fn ($row) => $row['status'] === 'Active');

        // ONE ROW PER COMMITMENT before any money is added up.
        //
        // DM's expired-documents feed returns the whole HISTORY of a document,
        // not just its current term — a trade licence renewed every year since
        // 2010 arrives as a dozen rows sharing one document_id. They are one
        // yearly cost, not a dozen, and counting each of them multiplied the
        // run-rate by however many old terms happened to be unsettled.
        // The latest term is the live one; the rest are past.
        $current = [];

        foreach ($active as $row) {
            $key = $row['source_type'] . ':' . $row['dm_group_id'];

            if (! isset($current[$key]) || $row['next_due'] > $current[$key]['next_due']) {
                $current[$key] = $row;
            }
        }

        // BDT only. There is no FX source in this application, so a USD
        // commitment cannot be added to a taka run-rate without inventing a rate
        // — the count of what was left out is shown on the tile instead.
        $bdt     = array_filter($current, fn ($row) => $row['currency'] === 'BDT');
        $foreign = array_filter($current, fn ($row) => $row['currency'] !== 'BDT');

        $monthly = array_sum(array_column($bdt, 'monthly_value'));

        $unpaid = array_filter($active, fn ($row) => ! $row['paid']);

        return [
            'active'        => count($active),
            'overdue'       => count(array_filter($unpaid, fn ($row) => $row['due_state'] === 'overdue')),
            'due_7'         => count(array_filter($unpaid, fn ($row) => $row['due_state'] === 'soon')),
            'due_30'        => count(array_filter($unpaid, fn ($row) => $row['due_in'] >= 0 && $row['due_in'] <= 30)),
            'monthly'       => $monthly,
            'annual'        => $monthly * 12,
            'foreign_count' => count($foreign),
            // What this desk has actually put in the accounts — the figure that
            // makes the join to the ledger visible. Counted in BDT only, for the
            // same reason as the run-rate.
            'recorded'      => array_sum(array_map(
                fn ($row) => $row['paid'] && $row['currency'] === 'BDT' ? (float) $row['amount'] : 0,
                $company
            )),
            'recorded_count' => count(array_filter($company, fn ($row) => $row['paid'])),
            // Shown on its own chip rather than hidden, so the desk never looks
            // like it quietly lost rows.
            'personal_count' => count($rows) - count($company),
        ];
    }

    /**
     * One commitment's cost expressed per month, so cycles of different lengths
     * can be added together. Without it the total is a mix of units.
     */
    private function monthlyValue(float $amount, string $cycle): float
    {
        return match ($cycle) {
            'Weekly'      => $amount * 52 / 12,
            'Monthly'     => $amount,
            'Quarterly'   => $amount / 3,
            'Half-Yearly' => $amount / 6,
            'Yearly'      => $amount / 12,
            // An unknown cycle contributes nothing rather than a month's worth. A
            // yearly licence counted as monthly would overstate the run-rate
            // twelvefold, and the tile is the figure people quote.
            default       => 0.0,
        };
    }

    /* ── Parsing DM's values ─────────────────────────────────────────────── */

    /** Parse a DM date without letting one bad value take the page down. */
    private function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * DM's `subscription_type` mapped onto the cycles this desk can normalise.
     * Anything unrecognised stays unknown rather than being assumed monthly.
     */
    private function normaliseCycle($value): string
    {
        $cycle = strtolower(trim((string) $value));

        return match (true) {
            $cycle === '' => '—',
            str_contains($cycle, 'week')                              => 'Weekly',
            str_contains($cycle, 'month')                             => 'Monthly',
            str_contains($cycle, 'quarter')                           => 'Quarterly',
            str_contains($cycle, 'half') || str_contains($cycle, 'semi') => 'Half-Yearly',
            str_contains($cycle, 'year') || str_contains($cycle, 'annual') => 'Yearly',
            default => ucfirst($cycle),
        };
    }

    /**
     * A document's cycle, inferred from how long its current term ran.
     *
     * DM does not state one, but the gap between the term's start and its expiry
     * is exactly that period — a licence issued 20 Apr 2025 and expiring 20 Apr
     * 2026 is annual. Snapped to the nearest standard cycle, and left unknown
     * when it fits none, so monthlyValue() cannot silently mis-scale it.
     */
    private function cycleFromSpan(?Carbon $from, ?Carbon $to): string
    {
        if (! $from || ! $to || $from->gte($to)) {
            return '—';
        }

        $months = $from->diffInMonths($to);

        return match (true) {
            $months <= 0  => '—',
            $months === 1 => 'Monthly',
            $months === 3 => 'Quarterly',
            $months === 6 => 'Half-Yearly',
            $months >= 11 && $months <= 13 => 'Yearly',
            default       => '—',
        };
    }

    /** BDT unless DM says otherwise, uppercased so 'usd' and 'USD' are one thing. */
    private function normaliseCurrency($value): string
    {
        $currency = strtoupper(trim((string) $value));

        return $currency !== '' ? $currency : 'BDT';
    }

    /**
     * A link, only if the value actually is one.
     *
     * DM's `url` column holds free text — in the sample data it reads "Hosting".
     * Rendering that as an href produces a link to a path on this ERP, so
     * anything that is not plainly http(s) is dropped and no link is shown.
     */
    private function externalUrl($value): ?string
    {
        $url = trim((string) $value);

        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
