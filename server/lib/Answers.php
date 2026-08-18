<?php
declare(strict_types=1);

/* ============================================================
   Answers — EON's offline voice.

   Nlu turns the boss's sentence into an intent and a language;
   this turns that intent into a grounded sentence, in the language
   he used. English, বাংলা and Banglish all land on the same
   handler, so a Bangla question is never a second-class citizen.

   Every number here comes from Analytics/Tools over the live ERP
   dataset — nothing is invented. This is the fallback brain: it
   runs when no ANTHROPIC_API_KEY is configured or the model call
   fails, so the panel always answers with real figures.
   ============================================================ */
final class Answers
{
    private Analytics $A;
    private Tools $T;
    private string $lang;      // 'en' | 'bn'  (Banglish is answered in Bangla)
    private array $used = [];

    private function __construct(array $D, ?int $company, Tools $tools, string $lang)
    {
        $this->A = new Analytics($D, $company);
        $this->T = $tools;
        $this->lang = $lang;
    }

    /** @return array{text:string,intent:?string,lang:string,tools_used:array,score:float} */
    public static function reply(string $question, array $D, ?int $company, Tools $tools, ?string $langHint = null): array
    {
        $p = Nlu::parse($question, $langHint);
        $lang = $p['lang'] === 'en' ? 'en' : 'bn';          // bn + bl → answer in Bangla
        $self = new self($D, $company, $tools, $lang);
        $text = $self->handle((string) ($p['intent'] ?? ''), $p, $question);
        return ['text' => $text, 'intent' => $p['intent'] ?? null, 'lang' => $lang, 'tools_used' => $self->used, 'score' => (float) ($p['score'] ?? 0)];
    }

    /* ---------- language helpers ---------- */

    /** pick the string for the answer language */
    private function t(string $en, string $bn): string { return $this->lang === 'bn' ? $bn : $en; }

    /** money, in the boss's own units: ৳12.5 L / ৳১২.৫ লক্ষ */
    private function m(float $n): string
    {
        if ($this->lang !== 'bn') return Analytics::bdtk($n);
        $a = abs($n); $s = $n < 0 ? '−' : '';
        if ($a >= 1e7) return $s . '৳' . $this->bnDigits(self::num($a / 1e7, $a >= 1e8 ? 0 : 1)) . ' কোটি';
        if ($a >= 1e5) return $s . '৳' . $this->bnDigits(self::num($a / 1e5, $a >= 1e6 ? 0 : 1)) . ' লক্ষ';
        if ($a >= 1e3) return $s . '৳' . $this->bnDigits(self::num($a / 1e3, 1)) . ' হাজার';
        return $s . '৳' . $this->bnDigits(self::num($a, 0));
    }
    /** a percentage in the answer language: 42% / ৪২% */
    private function pc(int|float|string|null $v): string { return $this->lang === 'bn' ? $this->bnDigits((string) $v) . '%' : ((string) $v) . '%'; }
    /** a date or YYYY-MM in the answer language — digits only, separators kept */
    private function d(?string $v): string { $v = (string) $v; return $this->lang === 'bn' ? $this->bnDigits($v) : $v; }

    /** Bengali numerals, so a Bangla answer reads as Bangla throughout */
    private function bnDigits(string $s): string
    {
        return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['০','১','২','৩','৪','৫','৬','৭','৮','৯'], $s);
    }
    private function exact(float $n): string { $s = Analytics::bdt($n); return $this->lang === 'bn' ? $this->bnDigits($s) : $s; }
    private static function num(float $v, int $dp): string
    {
        $s = number_format($v, $dp, '.', '');
        if ($dp > 0) $s = rtrim(rtrim($s, '0'), '.');
        return $s;
    }
    /** count words: 3 / ৩ */
    private function n(int|float $v): string
    {
        $s = (string) $v;
        return $this->lang === 'bn' ? str_replace(['0','1','2','3','4','5','6','7','8','9'], ['০','১','২','৩','৪','৫','৬','৭','৮','৯'], $s) : $s;
    }
    private function join(array $items): string
    {
        $items = array_values(array_filter($items, fn($x) => $x !== '' && $x !== null));
        return implode($this->t(', ', ', '), $items);
    }
    private function tool(string $name, array $in = []): mixed
    {
        $this->used[] = $name;
        return $this->T->run($name, $in);
    }

    /* ---------- the dispatcher ---------- */

    private function handle(string $intent, array $p, string $raw): string
    {
        $slots = $p['slots'] ?? [];
        return match ($intent) {
            'brief', 'focus'        => $this->brief($intent === 'focus'),
            'approvals'             => $this->approvals(),
            'risks', 'anomalies'    => $this->decisions(),
            'health'                => $this->health(),
            'since'                 => $this->brief(false),
            'forecast'              => $this->forecast(),
            'cash', 'bank_accounts' => $this->cash(),
            'petty_cash'            => $this->pettyCash(),
            'burn_runway'           => $this->runway(),
            'receivables'           => $this->receivables(),
            'payables'              => $this->payables(),
            'overdue_payments'      => $this->overdue(),
            'trial_balance'         => $this->trialBalance(),
            'balance_sheet'         => $this->balanceSheet(),
            'profit_loss', 'revenue' => $this->profit(),
            'expenses', 'expense_by_category', 'budget' => $this->expenses($slots),
            'account_ledger'        => $this->ledger($slots),
            'journal'               => $this->journal(),
            'loans'                 => $this->loans(),
            'advances'              => $this->advances(),
            'company_compare'       => $this->companies(),
            'payroll'               => $this->payroll($slots),
            'payroll_unpaid'        => $this->payrollUnpaid($slots),
            'deduction_rules'       => $this->deductionRules(),
            'overtime'              => $this->overtimeRule(),
            'headcount'             => $this->headcount(),
            'departments'           => $this->departments(),
            'attendance_today'      => $this->attendance(),
            'late_today'            => $this->attendance(true),
            'chronic_late'          => $this->chronicLate(),
            'leaves'                => $this->leaves(),
            'holidays'              => $this->holidays(),
            'employee_requests'     => $this->requests(),
            'evaluate_person'       => $this->person($raw, $slots),
            'online_now'            => $this->attendance(),
            'tasks'                 => $this->tasks(),
            'projects'              => $this->projects(),
            'todos'                 => $this->todos(),
            'pipeline'              => $this->pipeline(),
            'customers'             => $this->customers(),
            'suppliers'             => $this->suppliers(),
            'capabilities'          => $this->capabilities(),
            'greeting'              => $this->greeting(),
            'tax'                   => $this->tax(),
            default                 => $this->fallback($raw),
        };
    }

    /* ---------- finance ---------- */

    private function brief(bool $focusOnly): string
    {
        $b = $this->tool('get_brief');
        if ($focusOnly && !empty($b['decisions'])) {
            $d = $b['decisions'][0];
            return $this->t(
                'Do this first: ' . $d['title'] . ' — ' . ($d['recommend'] ?? ''),
                'সবার আগে এটি করুন: ' . $d['title'] . ' — ' . ($d['recommend'] ?? '')
            );
        }
        return (string) ($b['speak'] ?? $this->fallback(''));
    }

    private function cash(): string
    {
        $r = $this->tool('get_cash_position');
        $top = $r['accounts'][0] ?? null;
        $head = $this->t('Cash and bank: ', 'হাতে ও ব্যাংকে: ') . $this->m((float) $r['total'])
            . $this->t(' across ' . $this->n(count($r['accounts'])) . ' accounts.', ' — ' . $this->n(count($r['accounts'])) . 'টি অ্যাকাউন্টে।');
        if ($top) $head .= $this->t(' Largest: ' . $top['name'] . ' ' . $this->m((float) $top['balance']) . '.', ' সবচেয়ে বেশি ' . $top['name'] . '-এ ' . $this->m((float) $top['balance']) . '।');
        if (!empty($r['low'])) $head .= $this->t(' ' . $this->n(count($r['low'])) . ' account(s) are running low.', ' ' . $this->n(count($r['low'])) . 'টি অ্যাকাউন্টের ব্যালান্স কম।');
        return $head;
    }

    private function receivables(): string
    {
        $r = $this->tool('get_receivables');
        if (!$r['count']) return $this->t('Nobody owes us anything right now.', 'এই মুহূর্তে কারও কাছে পাওনা নেই।');
        $t = $this->t(
            'Receivable: ' . $this->m((float) $r['total']) . ' across ' . $this->n($r['count']) . ' invoices, of which ' . $this->m((float) $r['overdue_total']) . ' is overdue.',
            'পাওনা: ' . $this->m((float) $r['total']) . ' — ' . $this->n($r['count']) . 'টি বিলে, তার মধ্যে ' . $this->m((float) $r['overdue_total']) . ' মেয়াদ পার।'
        );
        $top = array_slice($r['by_party'] ?? [], 0, 3);
        if ($top) {
            $names = array_map(fn($p) => $p['party_name'] . ' ' . $this->m((float) $p['due']), $top);
            $t .= $this->t(' Biggest debtors: ' . $this->join($names) . '.', ' সবচেয়ে বেশি বাকি: ' . $this->join($names) . '।');
            $o = $r['by_party'][0];
            $t .= $this->t(' Chase ' . $o['party_name'] . ' first — ' . $this->n((int) $o['oldest']) . ' days old.', ' আগে ' . $o['party_name'] . '-কে তাগাদা দিন — ' . $this->n((int) $o['oldest']) . ' দিন হয়ে গেছে।');
        }
        return $t;
    }

    private function payables(): string
    {
        $r = $this->tool('get_payables');
        if (!$r['count']) return $this->t('Nothing is payable right now.', 'এই মুহূর্তে কোনো দেনা নেই।');
        $t = $this->t(
            'Payable: ' . $this->m((float) $r['total']) . ' across ' . $this->n($r['count']) . ' items, ' . $this->m((float) $r['overdue_total']) . ' already past due; ' . $this->m((float) $r['due_in_7_days']) . ' falls due within 7 days.',
            'দেনা: ' . $this->m((float) $r['total']) . ' — ' . $this->n($r['count']) . 'টি, তার মধ্যে ' . $this->m((float) $r['overdue_total']) . ' মেয়াদ পার; আগামী ৭ দিনে ' . $this->m((float) $r['due_in_7_days']) . ' দিতে হবে।'
        );
        $top = array_slice($r['by_party'] ?? [], 0, 3);
        if ($top) {
            $names = array_map(fn($p) => $p['party_name'] . ' ' . $this->m((float) $p['due']), $top);
            $t .= $this->t(' Largest: ' . $this->join($names) . '.', ' সবচেয়ে বড়: ' . $this->join($names) . '।');
        }
        return $t;
    }

    private function overdue(): string
    {
        $ar = $this->tool('get_receivables'); $ap = $this->tool('get_payables');
        return $this->t(
            'Overdue both ways: ' . $this->m((float) $ar['overdue_total']) . ' to collect (' . $this->n($ar['overdue_count']) . ' items) and ' . $this->m((float) $ap['overdue_total']) . ' to pay (' . $this->n($ap['overdue_count']) . ' items).',
            'দুই দিকেই মেয়াদ পার: আদায় করার আছে ' . $this->m((float) $ar['overdue_total']) . ' (' . $this->n($ar['overdue_count']) . 'টি), দিতে হবে ' . $this->m((float) $ap['overdue_total']) . ' (' . $this->n($ap['overdue_count']) . 'টি)।'
        );
    }

    private function profit(): string
    {
        $r = $this->tool('get_profit_and_loss');
        $net = (float) $r['net_profit'];
        $t = $this->t(
            'Month to date: revenue ' . $this->m((float) $r['income']) . ', direct cost ' . $this->m((float) $r['direct_cost']) . ', opex ' . $this->m((float) $r['opex']) . ' — net ' . ($net >= 0 ? 'profit ' : 'loss ') . $this->m(abs($net)) . ' (' . $r['margin_pct'] . '% margin).',
            'এ মাসে এ পর্যন্ত: আয় ' . $this->m((float) $r['income']) . ', সরাসরি খরচ ' . $this->m((float) $r['direct_cost']) . ', পরিচালন খরচ ' . $this->m((float) $r['opex']) . ' — নিট ' . ($net >= 0 ? 'লাভ ' : 'লোকসান ') . $this->m(abs($net)) . ' (মার্জিন ' . $this->pc($r['margin_pct']) . ')।'
        );
        // the ledger is not the whole business — say so when the desks invoiced more
        if (!empty($r['sales_invoiced']) && (float) $r['unposted_sales'] > 1000) {
            $t .= $this->t(
                ' Note: the desks invoiced ' . $this->m((float) $r['sales_invoiced']) . ' this month but only ' . $r['ledger_covers_pct'] . '% of it is posted to the ledger — ' . $this->m((float) $r['unposted_sales']) . ' of real sales is not journalised yet.',
                ' খেয়াল করুন: এ মাসে বিল হয়েছে ' . $this->m((float) $r['sales_invoiced']) . ', কিন্তু খাতায় উঠেছে মাত্র ' . $r['ledger_covers_pct'] . '% — ' . $this->m((float) $r['unposted_sales']) . ' এখনও জার্নাল হয়নি।'
            );
        }
        return $t;
    }

    private function trialBalance(): string
    {
        $r = $this->tool('get_trial_balance');
        return $this->t(
            'Trial balance ' . ($r['balanced'] ? 'balances' : 'does NOT balance') . ': debits ' . $this->m((float) $r['total_debit']) . ', credits ' . $this->m((float) $r['total_credit']) . '.',
            'ট্রায়াল ব্যালান্স ' . ($r['balanced'] ? 'মিলেছে' : 'মেলেনি') . ': ডেবিট ' . $this->m((float) $r['total_debit']) . ', ক্রেডিট ' . $this->m((float) $r['total_credit']) . '।'
        );
    }

    private function balanceSheet(): string
    {
        $r = $this->tool('get_balance_sheet');
        return $this->t(
            'Assets ' . $this->m((float) $r['total_assets']) . ', liabilities ' . $this->m((float) $r['total_liabilities']) . ', equity ' . $this->m((float) $r['total_equity']) . ($r['balanced'] ? ' — it balances.' : ' — it does not balance.'),
            'সম্পদ ' . $this->m((float) $r['total_assets']) . ', দায় ' . $this->m((float) $r['total_liabilities']) . ', মূলধন ' . $this->m((float) $r['total_equity']) . ($r['balanced'] ? ' — হিসাব মিলেছে।' : ' — হিসাব মেলেনি।')
        );
    }

    private function expenses(array $slots): string
    {
        $r = $this->tool('get_expenses_vs_budget', array_filter(['month' => $slots['month'] ?? null]));
        $t = $this->t(
            'Expenses ' . $r['month'] . ': ' . $this->m((float) $r['total_spent']) . ($r['total_budget'] ? ' against a budget of ' . $this->m((float) $r['total_budget']) : '') . '.',
            $this->d($r['month']) . ' মাসের খরচ: ' . $this->m((float) $r['total_spent']) . ($r['total_budget'] ? ', বাজেট ' . $this->m((float) $r['total_budget']) : '') . '।'
        );
        if (!empty($r['by_category'])) {
            $top = array_slice($r['by_category'], 0, 3);
            $names = array_map(fn($c) => ($c['category'] ?? '—') . ' ' . $this->m((float) ($c['spent'] ?? 0)), $top);
            $t .= $this->t(' Top: ' . $this->join($names) . '.', ' সবচেয়ে বেশি: ' . $this->join($names) . '।');
        }
        $t .= !empty($r['over'])
            ? $this->t(' Over budget: ' . $this->join(array_map(fn($x) => $x['category'] . ' ' . $x['pct'] . '%', $r['over'])) . '.', ' বাজেট ছাড়িয়েছে: ' . $this->join(array_map(fn($x) => $x['category'] . ' ' . $x['pct'] . '%', $r['over'])) . '।')
            : $this->t(' Nothing is over budget.', ' কোনো খাত বাজেট ছাড়ায়নি।');
        return $t;
    }

    private function pettyCash(): string
    {
        $floats = $this->A->dataset()['petty_cash_floats'] ?? [];
        $tx = $this->A->dataset()['petty_cash_transactions'] ?? [];
        if (!$floats) return $this->t('No petty-cash float is set up.', 'কোনো পেটি ক্যাশ ফ্লোট চালু নেই।');
        $lim = array_sum(array_map(fn($f) => (float) ($f['float_limit'] ?? 0), $floats));
        $names = array_map(fn($f) => (string) ($f['custodian'] ?? '—'), $floats);
        return $this->t(
            $this->n(count($floats)) . ' petty-cash float(s), limit ' . $this->m($lim) . ', held by ' . $this->join($names) . '; ' . $this->n(count($tx)) . ' movements on record.',
            $this->n(count($floats)) . 'টি পেটি ক্যাশ ফ্লোট, সীমা ' . $this->m($lim) . ', দায়িত্বে ' . $this->join($names) . '; ' . $this->n(count($tx)) . 'টি লেনদেন আছে।'
        );
    }

    private function runway(): string
    {
        $k = $this->A->kpis();
        $cash = (float) $k['cash']; $net = (float) $k['net_profit_mtd'];
        if ($net >= 0) return $this->t('Cash ' . $this->m($cash) . ' and this month is not loss-making, so there is no burn to run out of.', 'হাতে ' . $this->m($cash) . ', এ মাসে লোকসান নেই — তাই টাকা ফুরানোর ঝুঁকি নেই।');
        $months = abs($net) > 0 ? $cash / abs($net) : 0;
        return $this->t(
            'Burning about ' . $this->m(abs($net)) . ' a month against ' . $this->m($cash) . ' of cash — roughly ' . self::num($months, 1) . ' months of runway.',
            'মাসে প্রায় ' . $this->m(abs($net)) . ' লোকসান, হাতে ' . $this->m($cash) . ' — অর্থাৎ প্রায় ' . self::num($months, 1) . ' মাস চলবে।'
        );
    }

    private function forecast(): string
    {
        $r = $this->tool('forecast', ['months' => 3]);
        if (!is_array($r) || !empty($r['error']) || empty($r['months'])) {
            return $this->t('The forecasting service is not reachable on this host, so I can only give you today\'s position.', 'পূর্বাভাসের সেবাটি এই সার্ভারে চালু নেই, তাই এখনকার অবস্থাই বলতে পারি।') . ' ' . $this->cash();
        }
        $last = end($r['months']);
        return $this->t(
            'Forecast: month-end cash around ' . $this->m((float) ($last['cash'] ?? 0)) . ' by ' . ($last['month'] ?? '') . '.',
            'পূর্বাভাস: ' . ($last['month'] ?? '') . ' নাগাদ হাতে থাকবে প্রায় ' . $this->m((float) ($last['cash'] ?? 0)) . '।'
        );
    }

    private function ledger(array $slots): string
    {
        $code = (string) ($slots['account_code'] ?? '');
        if ($code === '') return $this->t('Which account code? For example 1311 for customer receivable or 6110 for salary expense.', 'কোন হিসাব নম্বর? যেমন ১৩১১ গ্রাহক পাওনা, ৬১১০ বেতন খরচ।');
        $r = $this->tool('get_account_ledger', ['code' => $code]);
        if (!empty($r['error'])) return $this->t('No account ' . $code . ' on the chart.', $this->d($code) . ' নামে কোনো হিসাব নেই।');
        return $this->t(
            'Account ' . $code . ' ' . ($r['name'] ?? '') . ': ' . $this->n((int) $r['postings']) . ' postings, closing balance ' . $this->exact((float) $r['closing_balance']) . '.',
            'হিসাব ' . $this->d($code) . ' ' . ($r['name'] ?? '') . ': ' . $this->n((int) $r['postings']) . 'টি এন্ট্রি, সমাপনী ব্যালান্স ' . $this->exact((float) $r['closing_balance']) . '।'
        );
    }

    private function journal(): string
    {
        $je = $this->A->dataset()['journal_entries'] ?? [];
        $recent = array_slice(array_reverse($je), 0, 3);
        $lines = array_map(fn($e) => ($e['date'] ?? '') . ' ' . ($e['description'] ?? $e['reference'] ?? ''), $recent);
        return $this->t(
            $this->n(count($je)) . ' journal entries on record. Latest: ' . $this->join($lines) . '.',
            'খাতায় ' . $this->n(count($je)) . 'টি জার্নাল এন্ট্রি আছে। সর্বশেষ: ' . $this->join($lines) . '।'
        );
    }

    private function loans(): string
    {
        $loans = $this->A->dataset()['loans'] ?? [];
        $open = array_values(array_filter($loans, fn($l) => (float) ($l['remaining_amount'] ?? 0) > 0));
        if (!$open) return $this->t('No staff loan is outstanding.', 'কোনো কর্মীর ঋণ বাকি নেই।');
        $rem = array_sum(array_map(fn($l) => (float) $l['remaining_amount'], $open));
        return $this->t(
            $this->n(count($open)) . ' staff loan(s) outstanding, ' . $this->m($rem) . ' still to recover.',
            $this->n(count($open)) . 'টি কর্মী ঋণ বাকি, আদায় করতে হবে ' . $this->m($rem) . '।'
        );
    }

    private function advances(): string
    {
        $adv = $this->A->dataset()['advance_salaries'] ?? [];
        $pend = array_values(array_filter($adv, fn($a) => in_array(strtolower((string) ($a['status'] ?? '')), ['pending', 'requested'], true)));
        $amt = array_sum(array_map(fn($a) => (float) $a['amount'], $pend));
        return $this->t(
            $this->n(count($adv)) . ' salary advances on record; ' . $this->n(count($pend)) . ' awaiting approval worth ' . $this->m($amt) . '.',
            'অগ্রিম বেতনের ' . $this->n(count($adv)) . 'টি আবেদন আছে; অনুমোদনের অপেক্ষায় ' . $this->n(count($pend)) . 'টি, মোট ' . $this->m($amt) . '।'
        );
    }

    private function companies(): string
    {
        $r = $this->tool('get_cash_position');
        $by = array_slice($r['by_company'] ?? [], 0, 5);
        if (!$by) return $this->t('No company split available.', 'কোম্পানিভিত্তিক ভাগ পাওয়া যায়নি।');
        $lines = array_map(fn($c) => $c['company'] . ' ' . $this->m((float) $c['total']), $by);
        return $this->t('Cash by company: ' . $this->join($lines) . '.', 'কোম্পানিভিত্তিক নগদ: ' . $this->join($lines) . '।');
    }

    private function tax(): string
    {
        return $this->t(
            'The ERP does not compute Bangladesh VAT or TDS yet — no Mushak 6.3 or 9.1 output, and payroll deducts no income tax. Statutory balances sit in 2270 Income Tax Payable and 2280 TDS/VDS Payable; I can read those, but the returns are prepared outside the system.',
            'ইআরপিতে ভ্যাট বা টিডিএস এখনও হিসাব হয় না — মুশক ৬.৩ বা ৯.১ তৈরি হয় না, বেতন থেকে আয়কর কাটাও নেই। ২২৭০ আয়কর প্রদেয় ও ২২৮০ টিডিএস/ভিডিএস প্রদেয় হিসাবে বকেয়া থাকে; আমি সেগুলো দেখাতে পারি, তবে রিটার্ন সিস্টেমের বাইরে তৈরি হয়।'
        );
    }

    /* ---------- people ---------- */

    private function payroll(array $slots): string
    {
        $r = $this->tool('get_payroll', array_filter(['month' => $slots['month'] ?? null]));
        $t = $this->t(
            'Payroll ' . $r['month'] . ': ' . $this->n((int) $r['heads']) . ' payslips, gross ' . $this->m((float) $r['gross']) . ', deductions ' . $this->m((float) $r['deductions']) . ', net ' . $this->m((float) $r['net']) . '.',
            $this->d($r['month']) . ' মাসের বেতন: ' . $this->n((int) $r['heads']) . 'টি পে-স্লিপ, মোট ' . $this->m((float) $r['gross']) . ', কর্তন ' . $this->m((float) $r['deductions']) . ', নিট ' . $this->m((float) $r['net']) . '।'
        );
        $t .= $r['pending_count']
            ? $this->t(' ' . $this->n((int) $r['pending_count']) . ' are still unpaid — ' . $this->m((float) $r['pending_net']) . '.', ' এখনও ' . $this->n((int) $r['pending_count']) . 'টি বাকি — ' . $this->m((float) $r['pending_net']) . '।')
            : $this->t(' All paid.', ' সব পরিশোধ হয়েছে।');
        return $t;
    }

    private function payrollUnpaid(array $slots): string
    {
        $r = $this->tool('get_payroll', array_filter(['month' => $slots['month'] ?? null]));
        if (!$r['pending_count']) return $this->t('Every payslip for ' . $r['month'] . ' is paid.', $this->d($r['month']) . ' মাসের সব বেতন পরিশোধ হয়েছে।');
        return $this->t(
            $this->n((int) $r['pending_count']) . ' payslips for ' . $r['month'] . ' are unpaid, ' . $this->m((float) $r['pending_net']) . ' in total. Release the late salaries before supplier bills.',
            $this->d($r['month']) . ' মাসের ' . $this->n((int) $r['pending_count']) . 'টি বেতন এখনও বাকি, মোট ' . $this->m((float) $r['pending_net']) . '। সরবরাহকারীর বিলের আগে বকেয়া বেতন ছাড়ুন।'
        );
    }

    private function deductionRules(): string
    {
        return $this->t(
            'Deductions follow the ERP\'s payroll service: daily rate = salary ÷ days in month, hourly = daily ÷ 9, per-minute = hourly ÷ 60. Absence costs a full daily rate per day; unpaid leave the same. Lateness is only charged once the month\'s late minutes reach 120 — a two-hour grace — and then every late minute counts. Leaving more than 10 minutes early is charged per minute unless approved leave covers the day. Overtime starts 60 minutes after shift end and is paid only to overtime-eligible staff.',
            'কর্তনের নিয়ম ইআরপির পে-রোল সার্ভিস অনুযায়ী: দৈনিক হার = বেতন ÷ মাসের দিন, ঘণ্টা = দৈনিক ÷ ৯, মিনিট = ঘণ্টা ÷ ৬০। অনুপস্থিত থাকলে দিনপ্রতি পুরো দৈনিক হার কাটা যায়, বিনা বেতনের ছুটিতেও তাই। দেরির জন্য কাটা হয় কেবল তখনই, যখন মাসের মোট দেরি ১২০ মিনিট ছোঁয় — অর্থাৎ দুই ঘণ্টা ছাড় — এরপর প্রতি মিনিট ধরা হয়। শিফট শেষের ১০ মিনিটের বেশি আগে গেলে মিনিট হিসেবে কাটা যায়, তবে অনুমোদিত ছুটি থাকলে নয়। ওভারটাইম শুরু হয় শিফট শেষের ৬০ মিনিট পর, আর তা কেবল ওভারটাইম-যোগ্য কর্মীরাই পান।'
        );
    }

    private function overtimeRule(): string
    {
        return $this->t(
            'Overtime counts only from 60 minutes after the shift ends, and only for staff marked overtime-eligible; it is paid at the per-minute rate (salary ÷ days ÷ 9 ÷ 60).',
            'ওভারটাইম গণনা শুরু হয় শিফট শেষ হওয়ার ৬০ মিনিট পর থেকে, আর কেবল ওভারটাইম-যোগ্য কর্মীদের জন্য; মিনিটপ্রতি হারে (বেতন ÷ দিন ÷ ৯ ÷ ৬০) দেওয়া হয়।'
        );
    }

    private function headcount(): string
    {
        $k = $this->A->kpis();
        return $this->t(
            'Headcount ' . $this->n((int) $k['headcount']) . ' active, monthly salary bill ' . $this->m((float) $k['monthly_payroll']) . '.',
            'কর্মী সংখ্যা ' . $this->n((int) $k['headcount']) . ' জন, মাসিক বেতন ' . $this->m((float) $k['monthly_payroll']) . '।'
        );
    }

    private function departments(): string
    {
        $emps = array_filter($this->A->dataset()['employees'] ?? [], fn($e) => ($e['status'] ?? 'active') === 'active');
        $by = [];
        foreach ($emps as $e) { $d = (string) ($e['department'] ?? '—'); $by[$d] = ($by[$d] ?? 0) + 1; }
        arsort($by);
        $lines = [];
        foreach (array_slice($by, 0, 6, true) as $d => $n) $lines[] = $d . ' ' . $this->n($n);
        return $this->t(
            $this->n(count($by)) . ' departments. Largest: ' . $this->join($lines) . '.',
            $this->n(count($by)) . 'টি বিভাগ। সবচেয়ে বড়: ' . $this->join($lines) . '।'
        );
    }

    private function attendance(bool $lateOnly = false): string
    {
        $r = $this->tool('get_attendance_today');
        if (!empty($r['weekend'])) return $this->t('It is the weekend — no attendance expected.', 'আজ সাপ্তাহিক ছুটি — উপস্থিতির প্রশ্ন নেই।');
        // nobody has punched yet: say that, rather than reporting everyone absent
        if (!empty($r['no_data_yet'])) {
            return $this->t(
                'No attendance has been recorded yet today. The last day on record is ' . $r['last_recorded_date'] . '.',
                'আজ এখনও কোনো হাজিরা ওঠেনি। সর্বশেষ হাজিরা ' . $this->d($r['last_recorded_date']) . ' তারিখের।'
            );
        }
        if ($lateOnly) {
            $names = array_map(fn($a) => $a['name'] . ' (' . $this->n((int) ($a['late_minutes'] ?? 0)) . $this->t(' min', ' মিনিট') . ')', array_slice($r['late_list'] ?? [], 0, 6));
            return $r['late']
                ? $this->t($this->n((int) $r['late']) . ' came late today: ' . $this->join($names) . '.', 'আজ ' . $this->n((int) $r['late']) . ' জন দেরিতে এসেছেন: ' . $this->join($names) . '।')
                : $this->t('Nobody was late today.', 'আজ কেউ দেরি করেননি।');
        }
        $den = (int) ($r['tracked'] ?: $r['total']);
        $t = $this->t(
            'Today: ' . $this->n((int) $r['present']) . ' of ' . $this->n($den) . ' tracked staff present (' . $r['present_pct'] . '%), ' . $this->n((int) $r['absent']) . ' absent, ' . $this->n((int) $r['late']) . ' late, ' . $this->n((int) $r['on_leave']) . ' on leave.',
            'আজ: হাজিরার আওতায় থাকা ' . $this->n($den) . ' জনের মধ্যে ' . $this->n((int) $r['present']) . ' জন উপস্থিত (' . $this->pc($r['present_pct']) . '), অনুপস্থিত ' . $this->n((int) $r['absent']) . ', দেরি ' . $this->n((int) $r['late']) . ', ছুটিতে ' . $this->n((int) $r['on_leave']) . '।'
        );
        if ($den < (int) $r['total']) $t .= $this->t(' (' . $this->n((int) $r['total']) . ' on the payroll; the rest are not on the attendance system.)', ' (বেতনভুক্ত ' . $this->n((int) $r['total']) . ' জন; বাকিরা হাজিরা ব্যবস্থার আওতায় নেই।)');
        if (!empty($r['absent_list'])) {
            $names = array_map(fn($a) => $a['name'], array_slice($r['absent_list'], 0, 5));
            $t .= $this->t(' Absent: ' . $this->join($names) . '.', ' অনুপস্থিত: ' . $this->join($names) . '।');
        }
        return $t;
    }

    private function chronicLate(): string
    {
        $r = $this->tool('get_attendance_patterns', ['days' => 30]);
        $c = $r['chronic_late'] ?? [];
        if (!$c) return $this->t('Nobody is habitually late over the last 30 days.', 'গত ৩০ দিনে নিয়মিত দেরি করেন এমন কেউ নেই।');
        $names = array_map(fn($x) => $x['name'] . ' (' . $this->n((int) $x['late_days']) . $this->t(' days', ' দিন') . ')', array_slice($c, 0, 5));
        return $this->t(
            $this->n(count($c)) . ' people are late on 30% or more of days: ' . $this->join($names) . '.',
            $this->n(count($c)) . ' জন ৩০% বা তার বেশি দিন দেরি করেন: ' . $this->join($names) . '।'
        );
    }

    private function leaves(): string
    {
        $r = $this->A->pendingLeaves();
        $n = is_array($r) ? count($r) : 0;
        if (!$n) return $this->t('No leave application is waiting.', 'কোনো ছুটির আবেদন অপেক্ষায় নেই।');
        $names = array_map(fn($l) => (string) ($l['name'] ?? $l['employee'] ?? '—'), array_slice($r, 0, 5));
        return $this->t(
            $this->n($n) . ' leave application(s) waiting: ' . $this->join($names) . '.',
            $this->n($n) . 'টি ছুটির আবেদন অপেক্ষায়: ' . $this->join($names) . '।'
        );
    }

    private function holidays(): string
    {
        $hol = $this->A->dataset()['holidays'] ?? [];
        $today = $this->A->today();
        $next = null;
        foreach ($hol as $h) { $d = substr((string) ($h['start_date'] ?? ''), 0, 10); if ($d >= $today && ($next === null || $d < $next['d'])) $next = ['d' => $d, 'n' => (string) $h['name']]; }
        if (!$next) return $this->t('No upcoming holiday on the calendar.', 'ক্যালেন্ডারে সামনে কোনো ছুটি নেই।');
        $days = (int) round((strtotime($next['d']) - strtotime($today)) / 86400);
        return $this->t(
            'Next holiday: ' . $next['n'] . ' on ' . $next['d'] . ' (' . $this->n($days) . ' days away).',
            'পরবর্তী ছুটি: ' . $next['n'] . ', ' . $this->d($next['d']) . ' তারিখে (' . $this->n($days) . ' দিন পর)।'
        );
    }

    private function requests(): string
    {
        $req = $this->A->dataset()['employee_requests'] ?? [];
        $open = array_values(array_filter($req, fn($r) => !in_array(strtolower((string) ($r['status'] ?? '')), ['closed', 'rejected', 'fulfilled'], true)));
        if (!$open) return $this->t('No employee request is open.', 'কর্মীদের কোনো আবেদন খোলা নেই।');
        $amt = array_sum(array_map(fn($r) => (float) ($r['amount'] ?? 0), $open));
        return $this->t(
            $this->n(count($open)) . ' employee request(s) open' . ($amt > 0 ? ', worth ' . $this->m($amt) : '') . '.',
            'কর্মীদের ' . $this->n(count($open)) . 'টি আবেদন খোলা আছে' . ($amt > 0 ? ', মোট ' . $this->m($amt) : '') . '।'
        );
    }

    private function person(string $raw, array $slots): string
    {
        $name = (string) ($slots['name_hint'] ?? '');
        if ($name === '') $name = $raw;
        $e = $this->A->findEmployee($name);
        if (!$e) return $this->t('I could not match that to anyone on the payroll.', 'ওই নামে কোনো কর্মী খুঁজে পাইনি।');
        $this->used[] = 'find_employee';
        $ev = $this->A->evaluate((int) $e['id']);
        if (!empty($ev['narrative'])) return (string) $ev['narrative'];
        return $this->t(
            $e['name'] . ' — ' . ($e['designation'] ?? '') . ', ' . ($e['department'] ?? '') . ', salary ' . $this->m((float) ($e['salary'] ?? 0)) . '.',
            $e['name'] . ' — ' . ($e['designation'] ?? '') . ', ' . ($e['department'] ?? '') . ', বেতন ' . $this->m((float) ($e['salary'] ?? 0)) . '।'
        );
    }

    /* ---------- work ---------- */

    private function tasks(): string
    {
        $r = $this->tool('get_tasks');
        $t = $this->t(
            $this->n((int) $r['overdue']) . ' tasks are overdue (' . $this->n((int) $r['overdue_high']) . ' high priority) out of ' . $this->n((int) $r['open']) . ' open; ' . $this->n((int) $r['closed_last_7_days']) . ' closed this week.',
            $this->n((int) $r['open']) . 'টি চলমান কাজের মধ্যে ' . $this->n((int) $r['overdue']) . 'টির সময় পার (' . $this->n((int) $r['overdue_high']) . 'টি জরুরি); এ সপ্তাহে শেষ হয়েছে ' . $this->n((int) $r['closed_last_7_days']) . 'টি।'
        );
        if (!empty($r['overloaded'])) {
            $o = $r['overloaded'][0];
            $t .= $this->t(' ' . $o['name'] . ' is overloaded with ' . $this->n((int) $o['open']) . ' open.', ' ' . $o['name'] . '-এর ওপর চাপ বেশি — ' . $this->n((int) $o['open']) . 'টি কাজ।');
        }
        return $t;
    }

    private function projects(): string
    {
        $r = $this->tool('get_projects');
        $t = $this->t(
            $this->n((int) $r['active']) . ' active projects, ' . $this->n(count($r['at_risk'])) . ' at risk.',
            $this->n((int) $r['active']) . 'টি চলমান প্রকল্প, ঝুঁকিতে ' . $this->n(count($r['at_risk'])) . 'টি।'
        );
        if (!empty($r['at_risk'])) {
            $w = $r['at_risk'][0];
            $t .= $this->t(' Worst: ' . $w['name'] . ' — ' . $w['progress'] . '% done at ' . $w['elapsed_pct'] . '% of the time.', ' সবচেয়ে খারাপ: ' . $w['name'] . ' — সময়ের ' . $w['elapsed_pct'] . '% পার, কাজ হয়েছে ' . $w['progress'] . '%।');
        }
        return $t;
    }

    private function todos(): string
    {
        $todos = $this->A->dataset()['office_todos'] ?? [];
        $open = array_values(array_filter($todos, fn($t) => strtolower((string) ($t['status'] ?? '')) !== 'completed'));
        if (!$open) return $this->t('The office to-do list is clear.', 'অফিসের কাজের তালিকা খালি।');
        $titles = array_map(fn($t) => (string) $t['title'], array_slice($open, 0, 4));
        return $this->t(
            $this->n(count($open)) . ' office to-dos open: ' . $this->join($titles) . '.',
            'অফিসের ' . $this->n(count($open)) . 'টি কাজ বাকি: ' . $this->join($titles) . '।'
        );
    }

    private function pipeline(): string
    {
        $r = $this->tool('get_pipeline');
        return $this->t(
            'Pipeline: ' . $this->n((int) $r['open']) . ' open leads worth ' . $this->m((float) $r['open_value']) . '; ' . $this->n((int) $r['won']) . ' won, ' . $this->n((int) $r['lost']) . ' lost' . ($r['conversion_pct'] !== null ? ' (' . $r['conversion_pct'] . '% conversion)' : '') . '. ' . $this->n((int) $r['stale_count']) . ' have gone cold.',
            'পাইপলাইন: ' . $this->n((int) $r['open']) . 'টি চলমান লিড, মূল্য ' . $this->m((float) $r['open_value']) . '; জিতেছি ' . $this->n((int) $r['won']) . 'টি, হেরেছি ' . $this->n((int) $r['lost']) . 'টি' . ($r['conversion_pct'] !== null ? ' (রূপান্তর ' . $r['conversion_pct'] . '%)' : '') . '। ঠান্ডা হয়ে গেছে ' . $this->n((int) $r['stale_count']) . 'টি।'
        );
    }

    private function customers(): string
    {
        $ar = $this->tool('get_receivables');
        $cust = $this->A->dataset()['customers'] ?? [];
        $top = array_slice($ar['by_party'] ?? [], 0, 3);
        $t = $this->t($this->n(count($cust)) . ' customers on file.', 'তালিকায় ' . $this->n(count($cust)) . ' জন গ্রাহক আছেন।');
        if ($top) {
            $names = array_map(fn($p) => $p['party_name'] . ' ' . $this->m((float) $p['due']), $top);
            $t .= $this->t(' Owing the most: ' . $this->join($names) . '.', ' সবচেয়ে বেশি বাকি: ' . $this->join($names) . '।');
        }
        return $t;
    }

    private function suppliers(): string
    {
        $ap = $this->tool('get_payables');
        $sup = $this->A->dataset()['suppliers'] ?? [];
        $top = array_slice(array_values(array_filter($ap['by_party'] ?? [], fn($p) => ($p['party_type'] ?? '') !== 'employee')), 0, 3);
        $t = $this->t($this->n(count($sup)) . ' suppliers on file.', 'তালিকায় ' . $this->n(count($sup)) . ' জন সরবরাহকারী আছেন।');
        if ($top) {
            $names = array_map(fn($p) => $p['party_name'] . ' ' . $this->m((float) $p['due']), $top);
            $t .= $this->t(' We owe the most to: ' . $this->join($names) . '.', ' সবচেয়ে বেশি দেনা: ' . $this->join($names) . '।');
        }
        return $t;
    }

    /* ---------- meta ---------- */

    private function approvals(): string
    {
        $r = $this->tool('get_approvals');
        if (!$r['count']) return $this->t('Your approval queue is empty.', 'আপনার অনুমোদনের তালিকা খালি।');
        $first = $r['items'][0];
        return $this->t(
            $this->n((int) $r['count']) . ' approvals are waiting on you — ' . $this->m((float) $r['amount']) . ' in total. Biggest: ' . $first['title'] . ($first['amount'] ? ' for ' . $this->exact((float) $first['amount']) : '') . '.',
            'আপনার অনুমোদনের অপেক্ষায় ' . $this->n((int) $r['count']) . 'টি — মোট ' . $this->m((float) $r['amount']) . '। সবচেয়ে বড়: ' . $first['title'] . ($first['amount'] ? ', ' . $this->exact((float) $first['amount']) : '') . '।'
        );
    }

    private function decisions(): string
    {
        $r = $this->tool('get_decisions');
        if (!$r) return $this->t('Nothing needs you right now.', 'এই মুহূর্তে আপনার জরুরি কিছু নেই।');
        $first = $r[0];
        return $this->t(
            $this->n(count($r)) . ' things need a decision. First: ' . $first['title'] . ' — ' . ($first['recommend'] ?? ''),
            $this->n(count($r)) . 'টি বিষয়ে সিদ্ধান্ত দরকার। প্রথমটি: ' . $first['title'] . ' — ' . ($first['recommend'] ?? '')
        );
    }

    private function health(): string
    {
        $k = $this->A->kpis();
        return $this->t(
            'Cash ' . $this->m((float) $k['cash']) . ', receivable ' . $this->m((float) $k['receivables']) . ', payable ' . $this->m((float) $k['payables']) . ', net this month ' . $this->m((float) $k['net_profit_mtd']) . ', ' . $this->n((int) $k['headcount']) . ' staff, ' . $this->n((int) $k['tasks_overdue']) . ' tasks overdue.',
            'হাতে ' . $this->m((float) $k['cash']) . ', পাওনা ' . $this->m((float) $k['receivables']) . ', দেনা ' . $this->m((float) $k['payables']) . ', এ মাসের নিট ' . $this->m((float) $k['net_profit_mtd']) . ', কর্মী ' . $this->n((int) $k['headcount']) . ' জন, সময় পার হওয়া কাজ ' . $this->n((int) $k['tasks_overdue']) . 'টি।'
        );
    }

    private function greeting(): string
    {
        $boss = (string) (Config::get('boss.name') ?? '');
        $first = trim(explode(' ', $boss)[0] ?? '');
        return $this->t(
            'Yes' . ($first ? ', ' . $first : '') . '. Ask me about cash, who owes us, what we owe, profit, payroll, attendance, tasks or a person by name.',
            'জি' . ($first ? ', ' . $first : '') . '। নগদ, পাওনা, দেনা, লাভ, বেতন, উপস্থিতি, কাজ — বা কারও নাম ধরে জিজ্ঞাসা করুন।'
        );
    }

    private function capabilities(): string
    {
        return $this->t(
            'I read the live ERP: cash and banks, receivables and payables with aging, profit and loss, trial balance and balance sheet, any account ledger, expenses against budget, payroll and its deduction rules, attendance and lateness, leaves and holidays, staff evaluation by name, tasks and projects, the CRM pipeline, ticket and visa sales, and the approvals waiting on you. Ask in English or বাংলা.',
            'আমি সরাসরি ইআরপি পড়ি: নগদ ও ব্যাংক, পাওনা-দেনা ও তার বয়স, লাভ-লোকসান, ট্রায়াল ব্যালান্স ও ব্যালান্স শিট, যেকোনো হিসাবের খতিয়ান, খরচ ও বাজেট, বেতন ও কর্তনের নিয়ম, উপস্থিতি ও দেরি, ছুটি ও সরকারি ছুটি, নাম ধরে কর্মীর মূল্যায়ন, কাজ ও প্রকল্প, সিআরএম পাইপলাইন, টিকিট ও ভিসা বিক্রি, আর আপনার অনুমোদনের তালিকা। বাংলা বা ইংরেজি — যেভাবে ইচ্ছা জিজ্ঞাসা করুন।'
        );
    }

    private function fallback(string $raw): string
    {
        // a name is the most likely thing an unmatched question is about
        if (trim($raw) !== '') {
            $e = $this->A->findEmployee($raw);
            if ($e) return $this->person($raw, []);
        }
        $k = $this->A->kpis();
        return $this->t(
            'I did not match that to a report. Right now: cash ' . $this->m((float) $k['cash']) . ', receivable ' . $this->m((float) $k['receivables']) . ', payable ' . $this->m((float) $k['payables']) . ', ' . $this->n((int) $k['absent_today']) . ' absent today, ' . $this->n((int) $k['tasks_overdue']) . ' tasks overdue. Ask me about cash, receivables, payables, profit, budget, attendance, payroll, pipeline, tasks, projects, approvals, or a person by name.',
            'প্রশ্নটি ঠিক ধরতে পারিনি। এখনকার অবস্থা: হাতে ' . $this->m((float) $k['cash']) . ', পাওনা ' . $this->m((float) $k['receivables']) . ', দেনা ' . $this->m((float) $k['payables']) . ', আজ অনুপস্থিত ' . $this->n((int) $k['absent_today']) . ' জন, সময় পার হওয়া কাজ ' . $this->n((int) $k['tasks_overdue']) . 'টি। নগদ, পাওনা, দেনা, লাভ, বাজেট, উপস্থিতি, বেতন, পাইপলাইন, কাজ, প্রকল্প, অনুমোদন — বা কারও নাম ধরে জিজ্ঞাসা করুন।'
        );
    }
}
