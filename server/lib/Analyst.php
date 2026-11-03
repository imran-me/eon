<?php
/* ============================================================
   EON · Analyst — exact answers for the owner's intents.

   IntentModel says WHAT is asked (92 intents learned from the owner's
   100,000 questions); Slots says WHO / WHEN / WHICH SECTION; each handler
   here computes the answer from the ERP dataset and says it in the boss's
   language. Nothing is estimated: a party the ERP does not have is named as
   missing, an empty period is said to be empty.

   Actions (entries, payments, reports, ledgers, navigation) are handed back
   to the panel as a command, where EON's action layer carries them out
   through the ERP's own forms behind the yes-gate.
   ============================================================ */
declare(strict_types=1);

final class Analyst
{
    /** intents the panel's action layer carries out: the sentence itself is the command */
    private const ACTIONS = ['খরচ এন্ট্রি', 'এজেন্ট লেজার স্টেটমেন্ট রিপোর্ট', 'রিপোর্ট - পিডিএফ/এক্সেল', 'এজেন্ট পেমেন্ট রিসিভ', 'ভেন্ডর পেমেন্ট', 'এজেন্ট লেজার খোলা',
        'ব্যাংক লেনদেন এন্ট্রি', 'খরচ রিপোর্ট ও বাজেট', 'লেজার দেখা', 'জার্নাল এন্ট্রি', 'ভেন্ডর স্টেটমেন্ট রিপোর্ট', 'ব্যাংক স্টেটমেন্ট', 'নতুন ভিসা অ্যাপ্লিকেশন (ট্রানজ্যাকশন)',
        'পেমেন্ট এন্ট্রি (ট্রানজ্যাকশন)', 'ইনকাম স্টেটমেন্ট রিপোর্ট', 'ভেন্ডর লেজার খোলা', 'টিকেট ইস্যু (ট্রানজ্যাকশন)', 'এজেন্ট লেজার অ্যাডজাস্টমেন্ট', 'কোম্পানি/জেনারেল লেজার',
        'রিশিডিউল (ট্রানজ্যাকশন)', 'পার্টি তৈরি/এডিট', 'নেভিগেশন - নতুন কন্ট্রাক্ট ফ্লাইট এন্ট্রি', 'বেতন প্রসেস/পেমেন্ট', 'পে-স্লিপ ও পেরোল রিপোর্ট', 'পার্টি স্টেটমেন্ট রিপোর্ট/শেয়ার',
        'ট্রায়াল ব্যালেন্স/ব্যালেন্স শিট', 'ক্যান্সেলেশন (ট্রানজ্যাকশন)', 'ভয়েড/রিফান্ড/রিইস্যু (ট্রানজ্যাকশন)', 'খরচ অ্যাপ্রুভাল', 'নতুন ফাইল/আপডেট (ট্রানজ্যাকশন)', 'পেন্ডিং অ্যাপ্রুভাল',
        'আগের উত্তরের রিপোর্ট/পিডিএফ', 'আগের উত্তর থেকে লেজারে যাওয়া', 'নেভিগেশন - কন্ট্রাক্ট ফ্লাইট পেজ', 'নেভিগেশন - কন্ট্রাক্ট ফাইল', 'নেভিগেশন - টিকেটিং', 'নেভিগেশন - ভিসা'];

    private static bool $bn = true;
    private static function t(string $en, string $bn): string { return self::$bn ? $bn : $en; }
    private static function m(float $v): string { return Phrase::money($v, self::$bn ? 'bn' : 'en'); }
    private static function n($v): string { return Phrase::n($v, self::$bn ? 'bn' : 'en'); }
    private static function in(?string $d, ?array $p): bool { return $p === null || ($d !== null && substr($d, 0, 10) >= $p[0] && substr($d, 0, 10) <= $p[1]); }
    private static function when(?array $p, string $defEn, string $defBn): string { return $p ? (self::$bn ? Phrase::bnDigits($p[3]) : $p[2]) : self::t($defEn, $defBn); }

    /** ['text'=>…] or ['client_command'=>…] or null (not handled — let the next layer try) */
    /** an intent the sentence's own shape makes certain — a PNR, the word voucher, collection, payroll — or null */
    public static function shape(string $q): ?string
    {
        $l = mb_strtolower($q);
        if ((preg_match('/\b(?=[a-z0-9]*\d)(?=[a-z0-9]*[a-z])[a-z0-9]{6}\b/i', $q) || preg_match('/\b\d{3}-?\d{10}\b/', $q))
            && preg_match('/টিকেট|টিকিট|ticket|pnr|তথ্য|দেখাও|info/u', $l) && !preg_match('/রিফান্ড|refund|রিইস্যু|reissue|void|ভয়েড|ক্যান্সেল|cancel|রিশিডিউল|reschedule/u', $l)) return 'টিকেট খোঁজা';
        if (preg_match('/ভাউচার|voucher/u', $l) && !preg_match('/এন্ট্রি দাও|entry dao|বানাও|করো/u', $l)) return 'ভাউচার/জার্নাল খোঁজা';
        if (preg_match('/কালেকশন|collection|টাকা আসছে|পেমেন্ট গেছে|ক্যাশ ফ্লো|cash ?flow/u', $l)) return 'ক্যাশ ব্যালেন্স ও ক্যাশ ফ্লো';
        if (preg_match('/পে-?রোল|পেরোল|payroll/u', $l) && !preg_match('/প্রসেস|process|পাঠাও|send/u', $l)) return 'বেতন জানা';
        if (preg_match('/(কোথায়|কোথায়|কোন খাতে|কোন জায়গায়|কোন জায়গায়|কেন|কিসে|where|why).*(লস|ক্ষতি|loss)|(লস|ক্ষতি|loss).*(কোথায়|কোথায়|কেন|কিসে|where|why)/u', $l)) return 'লসের কারণ';
        return null;
    }

    public static function handle(string $intent, string $q, array $D, bool $bn): ?array
    {
        self::$bn = $bn;
        if (in_array($intent, self::ACTIONS, true)) return ['client_command' => $q];
        $map = [
            'এজেন্টভিত্তিক লাভ' => 'agentProfit', 'এজেন্ট অ্যানালিটিক্স' => 'agentProfit',
            'ফ্লাইট কাউন্ট - এজেন্টভিত্তিক' => 'agentFlights',
            'খরচ জানা' => 'expenses',
            'সেকশনভিত্তিক লাভ' => 'sectionProfit', 'দৈনিক/সময়ভিত্তিক লাভ' => 'sectionProfit', 'লাভজনকতা তুলনা' => 'sectionProfit', 'সেকশন তুলনা ও র‍্যাংকিং' => 'sectionProfit', 'ট্রেন্ড ও তুলনা' => 'sectionProfit',
            'এজেন্ট ব্যালেন্স/ডিউ' => 'agentDue', 'ডিউ/বকেয়া' => 'agentDue', 'ট্রাভেল অ্যাকাউন্টস' => 'agentDue',
            'ভেন্ডর পেয়েবল' => 'vendor', 'ভেন্ডরভিত্তিক খরচ/লাভ' => 'vendor',
            'টিকেট কাউন্ট/লিস্ট' => 'tickets',
            'ফ্লাইট লিস্ট - এয়ারলাইন/রুট' => 'flights', 'ফ্লাইট লিস্ট - সময়ভিত্তিক' => 'flights', 'ক্যান্সেলড ফ্লাইট কাউন্ট' => 'flights', 'ফ্লাইট ওপেন/খোঁজা' => 'flights', 'সিট ইনভেন্টরি' => 'flights',
            'ব্যাংক ব্যালেন্স' => 'banks',
            'বেতন জানা' => 'salary', 'কর্মী/HR' => 'salary',
            'ভিসা স্ট্যাটাস' => 'visa', 'ভিসা কাউন্ট/অ্যানালিটিক্স' => 'visa',
            'ফাইল খোঁজা/স্ট্যাটাস' => 'files', 'ফাইল কাউন্ট/অ্যানালিটিক্স' => 'files',
            'ক্যাশ ব্যালেন্স ও ক্যাশ ফ্লো' => 'cashflow', 'ক্যাশ/ব্যাংক' => 'cashflow',
            'ভাউচার/জার্নাল খোঁজা' => 'vouchers', 'জার্নাল/ভাউচার' => 'vouchers',
            'টিকেট খোঁজা' => 'ticketFind',
            'লিড/ক্লায়েন্ট' => 'leads', 'অপারেশন স্ট্যাটাস' => 'projects', 'রিস্ক অ্যানালাইসিস' => 'projects',
            'পার্টি তালিকা ও বকেয়া' => 'agentDue', 'বাজারে পাওনা/দেনা' => 'agentDue',
            'লাভ-ক্ষতি - কন্ট্রাক্ট ফ্লাইট' => 'flightProfit', 'টিকেটিং লাভ/কমিশন' => 'ticketProfit',
            'ট্রাভেল সামারি' => 'travelSummary', 'লসের কারণ' => 'losses',
            'রিশিডিউলের অ্যাকাউন্টস ইমপ্যাক্ট' => 'changes',
        ];
        // a statement / ledger looked at, or a follow-up on the last answer: the panel carries those
        if (in_array($intent, ['পার্টি স্টেটমেন্ট দেখা', 'ফলো-আপ (আগের উত্তরের প্রেক্ষিতে)', 'আগের উত্তর ছোট/বড় করা'], true)) return ['client_command' => $q];
        $h = $map[$intent] ?? null;
        if (!$h) return null;
        $t = self::$h($q, $D);
        return $t === null ? null : ['text' => $t];
    }

    private static function partyOrSay(string $q, array $D, array $kinds): array
    {
        $p = Slots::party($q, $D, $kinds);
        if ($p && isset($p['none'])) return [null, self::t("I don't find \"{$p['none']}\" among the ERP's parties — check the spelling, or it may not be entered yet.", "ERP-তে \"{$p['none']}\" নামে কোনো party পাইনি — বানানটা দেখুন, নাকি এখনো এন্ট্রি হয়নি।")];
        return [$p, null];
    }

    /* ---------- profit by agent (tickets + contract flights) ---------- */
    private static function agentProfit(string $q, array $D): ?string
    {
        [$p, $miss] = self::partyOrSay($q, $D, ['customer']); if ($miss) return $miss;
        // "টিকেটে কত লাভ" with no agent named is the ticket business's profit, not a ranking of agents
        if (!$p && preg_match('/টিকেট|টিকিট|ticket/u', mb_strtolower($q)) && !preg_match('/এজেন্ট|agent|কার থেকে|কোন এজেন্ট/u', mb_strtolower($q))) return self::ticketProfit($q, $D);
        $per = Slots::period($q);
        $cost = []; foreach ($D['ticket_purchases'] ?? [] as $x) $cost[$x['id']] = (float) ($x['total'] ?? 0);
        $agg = [];
        foreach ($D['ticket_index'] ?? [] as $x) { if (!$x['client'] || !self::in($x['date'], $per) || in_array($x['status'], ['cancelled', 'void'], true)) continue;
            $a = &$agg[(string) $x['client']]; $a['n'] = ($a['n'] ?? 0) + 1; $a['sale'] = ($a['sale'] ?? 0) + (float) $x['sale_price']; $a['profit'] = ($a['profit'] ?? 0) + (float) $x['sale_price'] - ($cost[$x['purchase_id']] ?? 0); unset($a); }
        $when = self::when($per, 'over the period on record', 'রেকর্ডের পুরো সময়ে');
        if ($p) {
            $a = $agg[$p['name']] ?? null;
            if (!$a) return self::t("{$p['name']} has no ticket sales $when.", "{$when} {$p['name']}-এর কোনো ticket sale নেই।");
            return self::t(sprintf('%s, %s: %d tickets, sales %s, profit %s (%s margin).', $p['name'], $when, $a['n'], self::m($a['sale']), self::m($a['profit']), round(100 * $a['profit'] / max(1, $a['sale']), 1) . '%'),
                sprintf('%s %s থেকে লাভ %s — %sটা ticket, sale %s, margin %s%%।', $when, $p['name'], self::m($a['profit']), self::n($a['n']), self::m($a['sale']), self::n(round(100 * $a['profit'] / max(1, $a['sale']), 1))));
        }
        if (!$agg) return self::t("No ticket sales $when.", "{$when} কোনো ticket sale নেই।");
        uasort($agg, fn($x, $y) => $y['profit'] <=> $x['profit']);
        $top = array_slice($agg, 0, 5, true); $parts = [];
        foreach ($top as $k => $a) $parts[] = $k . ' ' . self::m($a['profit']);
        return self::t('Profit by agent, ' . $when . ': ' . implode('; ', $parts) . '.', $when . ' এজেন্টভিত্তিক লাভ: ' . implode('; ', $parts) . '।');
    }

    /* ---------- contract flights / passengers given to an agent ---------- */
    private static function agentFlights(string $q, array $D): ?string
    {
        [$p, $miss] = self::partyOrSay($q, $D, ['customer']); if ($miss) return $miss;
        $per = Slots::period($q); $when = self::when($per, 'in all', 'মোট');
        $rows = array_values(array_filter($D['contract_flight_passengers'] ?? [], fn($x) => self::in($x['departure_at'], $per) && (!$p || (string) $x['agent'] === $p['name'])));
        $fl = array_unique(array_map(fn($x) => $x['flight_number'], $rows));
        if ($p) return self::t(sprintf('%s: %d passengers on %d contract flights %s%s.', $p['name'], count($rows), count($fl), $when, $fl ? ' (' . implode(', ', $fl) . ')' : ''),
            sprintf('%s %s-কে %sটা contract flight-এ %sজন passenger দেওয়া হয়েছে%s।', $when, $p['name'], self::n(count($fl)), self::n(count($rows)), $fl ? ' (' . implode(', ', $fl) . ')' : ''));
        $by = []; foreach ($rows as $x) $by[(string) $x['agent']] = ($by[(string) $x['agent']] ?? 0) + 1; arsort($by);
        $parts = []; foreach (array_slice($by, 0, 6, true) as $k => $c) $parts[] = "$k " . self::n($c);
        return self::t("Contract-flight passengers by agent, $when: " . implode('; ', $parts) . '.', "$when এজেন্টভিত্তিক contract flight passenger: " . implode('; ', $parts) . '।');
    }

    /* ---------- expenses ---------- */
    private static function expenses(string $q, array $D): ?string
    {
        $per = Slots::period($q) ?? [date('Y-m-01'), date('Y-m-d'), 'this month', 'এই মাসে'];
        $co = Slots::company($q, $D);
        $rows = array_values(array_filter($D['expenses'] ?? [], fn($e) => self::in($e['expense_date'], $per) && (!$co || (int) $e['company_id'] === (int) $co['id'])));
        $l = mb_strtolower($q); $cat = null;
        foreach ($rows as $e) { $c = mb_strtolower((string) $e['category']); if ($c !== '' && (str_contains($l, $c) || (mb_strlen($c) > 4 && str_contains($l, mb_substr($c, 0, 5))))) { $cat = $e['category']; break; } }
        if ($cat) $rows = array_values(array_filter($rows, fn($e) => $e['category'] === $cat));
        $sum = array_sum(array_map(fn($e) => (float) $e['amount'], $rows));
        $who = ($co ? $co['name'] . ' ' : '') . ($cat ? "($cat) " : '');
        if (!$rows) return self::t("No expenses {$who}recorded {$per[2]}.", "{$per[3]} {$who}কোনো খরচ এন্ট্রি নেই।");
        $by = []; foreach ($rows as $e) $by[(string) ($e['category'] ?: '—')] = ($by[(string) ($e['category'] ?: '—')] ?? 0) + (float) $e['amount']; arsort($by);
        $parts = []; foreach (array_slice($by, 0, 4, true) as $k => $v) $parts[] = "$k " . self::m($v);
        return self::t(sprintf('%s%s spending: %s across %d entries. Largest: %s.', ucfirst($per[2]) . ' ', $who, self::m($sum), count($rows), implode('; ', $parts)),
            sprintf('%s %sখরচ হয়েছে %s — %sটা এন্ট্রি। বড় খাত: %s।', $per[3], $who, self::m($sum), self::n(count($rows)), implode('; ', $parts)));
    }

    /* ---------- profit by section (company) from the journal ---------- */
    private static function sectionProfit(string $q, array $D): ?string
    {
        $per = Slots::period($q) ?? [date('Y-m-01'), date('Y-m-d'), 'this month', 'এই মাসে'];
        $co = Slots::company($q, $D);
        $type = []; foreach ($D['accounts'] ?? [] as $a) $type[$a['id']] = strtolower((string) $a['type']);
        $name = []; foreach ($D['companies'] ?? [] as $c) $name[$c['id']] = (string) $c['name'];
        $pl = [];
        foreach ($D['journal_entries'] ?? [] as $j) {
            if (!self::in($j['date'], $per) || ($co && (int) $j['company_id'] !== (int) $co['id'])) continue;
            $k = $name[$j['company_id']] ?? 'Group';
            foreach ((array) ($j['items'] ?? []) as $it) { $ty = $type[$it['account_id'] ?? 0] ?? '';
                if (str_contains($ty, 'income') || str_contains($ty, 'revenue')) $pl[$k]['in'] = ($pl[$k]['in'] ?? 0) + (float) $it['credit'] - (float) $it['debit'];
                elseif (str_contains($ty, 'expense') || str_contains($ty, 'cost')) $pl[$k]['out'] = ($pl[$k]['out'] ?? 0) + (float) $it['debit'] - (float) $it['credit']; }
        }
        if (!$pl) return self::t("Nothing is posted to the journal {$per[2]}" . ($co ? " for {$co['name']}" : '') . '.', "{$per[3]} " . ($co ? "{$co['name']}-এর " : '') . 'journal-এ কোনো আয়-ব্যয় পোস্ট হয়নি।');
        uasort($pl, fn($x, $y) => (($y['in'] ?? 0) - ($y['out'] ?? 0)) <=> (($x['in'] ?? 0) - ($x['out'] ?? 0)));
        $parts = []; foreach ($pl as $k => $v) $parts[] = $k . ': ' . self::t('income ', 'আয় ') . self::m($v['in'] ?? 0) . ', ' . self::t('profit ', 'লাভ ') . self::m(($v['in'] ?? 0) - ($v['out'] ?? 0));
        return self::t(ucfirst($per[2]) . ' by section — ' . implode('; ', array_slice($parts, 0, 6)) . '.', $per[3] . ' সেকশনভিত্তিক — ' . implode('; ', array_slice($parts, 0, 6)) . '।');
    }

    /* ---------- what an agent / client owes ---------- */
    private static function agentDue(string $q, array $D): ?string
    {
        // "বিএসপি কে কত দিতে হবে" is a due too — but BSP is a vendor: what WE owe, not what they owe us
        $any = Slots::party($q, $D, ['customer', 'supplier']);
        if ($any && !isset($any['none']) && $any['kind'] === 'supplier') return self::vendor($q, $D);
        if (preg_match('/দিতে হবে|দেনা|payable|we owe|ভেন্ডর|vendor|সাপ্লায়ার|supplier/u', mb_strtolower($q)) && !$any) return self::vendor($q, $D);
        [$p, $miss] = self::partyOrSay($q, $D, ['customer']); if ($miss) return $miss;
        $bal = []; foreach ($D['party_transactions'] ?? [] as $x) if ($x['party_type'] === 'customer' && !preg_match('/super ?admin/i', (string) $x['party_name'])) $bal[(string) $x['party_name']] = ($bal[(string) $x['party_name']] ?? 0) + (float) $x['debit'] - (float) $x['credit'];
        if ($p) {
            $b = $bal[$p['name']] ?? 0;
            $open = array_values(array_filter($D['ticket_sales'] ?? [], fn($s) => (string) $s['client'] === $p['name'] && (float) $s['due_amount'] > 0.5));
            usort($open, fn($a, $c) => strcmp((string) $a['date'], (string) $c['date']));
            $age = $open ? (int) floor((time() - strtotime((string) $open[0]['date'])) / 86400) : null;
            if ($b <= 0.5 && !$open) return self::t("{$p['name']} owes nothing" . ($b < -0.5 ? '; they have ' . self::m(-$b) . ' paid in advance.' : '.'), "{$p['name']}-এর কাছে কোনো টাকা পাওনা নেই" . ($b < -0.5 ? '; উল্টো ' . self::m(-$b) . ' advance জমা আছে।' : '।'));
            return self::t(sprintf('%s owes %s by their ledger%s.', $p['name'], self::m(max(0, $b)), $age !== null ? sprintf('; the oldest unpaid invoice (%s) is %d days old', $open[0]['invoice'], $age) : ''),
                sprintf('ledger অনুযায়ী %s-এর কাছে পাওনা %s%s।', $p['name'], self::m(max(0, $b)), $age !== null ? sprintf('; সবচেয়ে পুরনো unpaid invoice (%s) %s দিনের', $open[0]['invoice'], self::n($age)) : ''));
        }
        $owe = array_filter($bal, fn($v) => $v > 0.5); arsort($owe);
        $parts = []; foreach (array_slice($owe, 0, 5, true) as $k => $v) $parts[] = "$k " . self::m($v);
        return self::t(sprintf('Agents and clients owe %s in all. Largest: %s.', self::m(array_sum($owe)), implode('; ', $parts)), sprintf('এজেন্ট-ক্লায়েন্টদের কাছে মোট পাওনা %s। বেশি যাদের কাছে: %s।', self::m(array_sum($owe)), implode('; ', $parts)));
    }

    /* ---------- a vendor: bought, paid, due ---------- */
    private static function vendor(string $q, array $D): ?string
    {
        [$p, $miss] = self::partyOrSay($q, $D, ['supplier']); if ($miss) return $miss;
        $per = Slots::period($q); $when = self::when($per, 'in all', 'মোট');
        $rows = array_values(array_filter($D['ticket_purchases'] ?? [], fn($x) => self::in($x['date'], $per) && (!$p || (string) $x['vendor'] === $p['name'])));
        $bought = array_sum(array_map(fn($x) => (float) $x['total'], $rows)); $due = array_sum(array_map(fn($x) => (float) ($x['due_amount'] ?? 0), $rows));
        if ($p) return self::t(sprintf('%s, %s: %d purchases worth %s, paid %s, still due %s.', $p['name'], $when, count($rows), self::m($bought), self::m($bought - $due), self::m($due)),
            sprintf('%s %s থেকে %sটা purchase — %s; পরিশোধ %s, এখনো due %s।', $when, $p['name'], self::n(count($rows)), self::m($bought), self::m($bought - $due), self::m($due)));
        $by = []; foreach ($rows as $x) $by[(string) ($x['vendor'] ?: '—')] = ($by[(string) ($x['vendor'] ?: '—')] ?? 0) + (float) ($x['due_amount'] ?? 0); arsort($by);
        $parts = []; foreach (array_slice($by, 0, 5, true) as $k => $v) $parts[] = "$k " . self::m($v);
        return self::t("Vendor dues, $when: " . self::m($due) . '. Largest: ' . implode('; ', $parts) . '.', "$when vendor-দের due " . self::m($due) . '। বেশি: ' . implode('; ', $parts) . '।');
    }

    /* ---------- tickets: count and status ---------- */
    private static function tickets(string $q, array $D): ?string
    {
        [$p, $miss] = self::partyOrSay($q, $D, ['customer', 'supplier']); if ($miss) return $miss;
        $per = Slots::period($q); $when = self::when($per, 'in all', 'মোট');
        $rows = array_values(array_filter($D['ticket_index'] ?? [], fn($x) => self::in($x['date'], $per) && (!$p || $x['client'] === $p['name'] || $x['vendor'] === $p['name'])));
        $st = []; foreach ($rows as $x) $st[(string) ($x['status'] ?: '—')] = ($st[(string) ($x['status'] ?: '—')] ?? 0) + 1; arsort($st);
        $parts = []; foreach ($st as $k => $c) $parts[] = "$k " . self::n($c);
        $who = $p ? $p['name'] . ' ' : '';
        return self::t(sprintf('%s%s: %d tickets (%s).', $who, $when, count($rows), implode(', ', $parts)), sprintf('%s %s%sটা ticket (%s)।', $when, $who ? $who . '— ' : '', self::n(count($rows)), implode(', ', $parts)));
    }

    /* ---------- contract flights: by period, airline/route, cancelled, seats ---------- */
    private static function flights(string $q, array $D): ?string
    {
        $per = Slots::period($q); $when = self::when($per, 'on record', 'রেকর্ডে');
        // flights are asked about ahead: "this month / this week" means the whole of it, not up to today
        if ($per && $per[1] === date('Y-m-d') && preg_match('/month|মাস/u', $per[2] . $per[3])) $per[1] = date('Y-m-t');
        if ($per && $per[1] === date('Y-m-d') && preg_match('/week|সপ্তাহ/u', $per[2] . $per[3])) $per[1] = date('Y-m-d', strtotime('+6 days', strtotime($per[0])));
        $l = mb_strtolower($q);
        $F = array_values(array_filter($D['contract_flights'] ?? [], fn($f) => self::in($f['departure_at'], $per)));
        if (preg_match('/\b([a-z]{2,3}-?\d{2,4})\b/i', $q, $m)) { $code = strtoupper(str_replace('-', '', $m[1])); $F = array_values(array_filter($F, fn($f) => str_replace('-', '', strtoupper((string) $f['flight_number'])) === $code)); }
        if (preg_match('/ক্যান্সেল|ক্যানসেল|বাতিল|cancel/u', $l)) $F = array_values(array_filter($F, fn($f) => preg_match('/cancel/i', (string) $f['status'])));
        if (!$F) return self::t("No contract flights $when matching that.", "$when এমন কোনো contract flight নেই।");
        $seats = array_sum(array_map(fn($f) => (int) $f['total_seats'], $F)); $sold = array_sum(array_map(fn($f) => (int) $f['seats_sold'], $F));
        $list = implode('; ', array_map(fn($f) => $f['flight_number'] . ' ' . substr((string) $f['departure_at'], 0, 16) . ' (' . (int) $f['seats_sold'] . '/' . (int) $f['total_seats'] . ')', array_slice($F, 0, 8)));
        return self::t(sprintf('%d contract flights %s — %d of %d seats sold (%d unsold): %s.', count($F), $when, $sold, $seats, $seats - $sold, $list),
            sprintf('%s %sটা contract flight — %sটা seat-এর মধ্যে %sটা বিক্রি, %sটা unsold: %s।', $when, self::n(count($F)), self::n($seats), self::n($sold), self::n($seats - $sold), $list));
    }

    /* ---------- a person's salary, or the month's payroll ---------- */
    private static function salary(string $q, array $D): ?string
    {
        if (class_exists('Ops') && ($o = Ops::answer($q, $D, self::$bn ? 'bn' : 'en'))) return $o['text'];
        // the person named — among staff who actually draw a salary; two of the same name are asked about, never guessed
        $paid = []; foreach ($D['payroll'] ?? [] as $r) $paid[(int) $r['user_id']] = true;
        $emp = null; $hits = [];
        foreach (preg_split('/[\s,?।!]+/u', $q) ?: [] as $w) {
            $w = preg_replace('/(দের|য়ের|এর|ের|কে|র)$/u', '', $w) ?? $w; $k = Grammar::skeleton($w); if (mb_strlen($k) < 3) continue;
            foreach ($D['employees'] ?? [] as $e) {
                if (($e['status'] ?? '') !== 'active' || (!isset($paid[(int) $e['id']]) && (float) $e['salary'] <= 0)) continue;
                foreach (preg_split('/\s+/', (string) $e['name']) ?: [] as $part) if (Grammar::skeleton($part) === $k) { $hits[$e['id']] = $e; break; }
            }
            if ($hits) break;
        }
        if (count($hits) > 1) return self::t('Which one — ' . implode(', or ', array_map(fn($e) => $e['name'], $hits)) . '?', 'কার কথা বলছেন — ' . implode(', নাকি ', array_map(fn($e) => $e['name'], $hits)) . '?');
        if ($hits) $emp = array_values($hits)[0];
        $rows = $D['payroll'] ?? []; if (!$rows) return null;
        $months = array_unique(array_map(fn($r) => (string) $r['month_key'], $rows)); rsort($months); $mk = $months[0];
        if ($emp) {
            $mine = array_values(array_filter($rows, fn($r) => (int) $r['user_id'] === (int) $emp['id'])); usort($mine, fn($a, $b) => strcmp((string) $b['month_key'], (string) $a['month_key']));
            if (!$mine) return self::t("{$emp['name']}'s monthly salary is " . self::m((float) $emp['salary']) . '; no payslip yet.', "{$emp['name']}-এর মাসিক বেতন " . self::m((float) $emp['salary']) . '; এখনো কোনো payslip নেই।');
            $r = $mine[0]; $mon = Phrase::monthName((string) $r['month_key'], self::$bn ? 'bn' : 'en');
            return self::t(sprintf('%s: %s — gross %s, deductions %s, net %s (%s).', $emp['name'], $mon, self::m((float) $r['gross_salary']), self::m((float) $r['total_deductions']), self::m((float) $r['net_salary']), $r['status']),
                sprintf('%s — %s: gross %s, কাটা %s, net %s (%s)।', $emp['name'], $mon, self::m((float) $r['gross_salary']), self::m((float) $r['total_deductions']), self::m((float) $r['net_salary']), strtolower((string) $r['status']) === 'paid' ? 'পরিশোধিত' : 'বাকি'));
        }
        $cur = array_filter($rows, fn($r) => $r['month_key'] === $mk);
        $g = array_sum(array_map(fn($r) => (float) $r['gross_salary'], $cur)); $n = array_sum(array_map(fn($r) => (float) $r['net_salary'], $cur)); $d = array_sum(array_map(fn($r) => (float) $r['total_deductions'], $cur));
        $mon = Phrase::monthName($mk, self::$bn ? 'bn' : 'en');
        return self::t(sprintf('%s payroll: %d payslips, gross %s, deductions %s, net %s.', $mon, count($cur), self::m($g), self::m($d), self::m($n)), sprintf('%s-এর payroll: %sটা payslip, gross %s, কাটা %s, net %s।', $mon, self::n(count($cur)), self::m($g), self::m($d), self::m($n)));
    }

    /* ---------- visas: one applicant's, or counts ---------- */
    private static function visa(string $q, array $D): ?string
    {
        $V = $D['visa_processes'] ?? []; if (!$V) return null;
        $per = Slots::period($q);
        foreach ($V as $v) { $k = Grammar::skeleton((string) $v['applicant']); if (mb_strlen($k) >= 4 && str_contains(Grammar::skeleton($q), $k))
            return self::t(sprintf('%s — %s %s visa: %s (%s)%s.', $v['applicant'], $v['country'], $v['visa_category'], $v['status'], $v['stage'] ?? '', $v['travel_date'] ? ', travel ' . $v['travel_date'] : ''),
                sprintf('%s — %s %s visa: %s (%s)%s।', $v['applicant'], $v['country'], $v['visa_category'], $v['status'], $v['stage'] ?? '', $v['travel_date'] ? ', যাত্রা ' . Phrase::bnDigits((string) $v['travel_date']) : '')); }
        $st = []; foreach ($V as $v) $st[(string) ($v['status'] ?: '—')] = ($st[(string) ($v['status'] ?: '—')] ?? 0) + 1; arsort($st);
        $parts = []; foreach ($st as $k => $c) $parts[] = "$k " . self::n($c);
        return self::t(sprintf('%d visa processes: %s.', count($V), implode(', ', $parts)), sprintf('মোট %sটা visa process: %s।', self::n(count($V)), implode(', ', $parts)));
    }

    /* ---------- contract files: one file, or counts ---------- */
    private static function files(string $q, array $D): ?string
    {
        $F = $D['contract_files'] ?? []; if (!$F) return null;
        if (preg_match('/\b([A-Z]{1,4}-?\d{2,6})\b/i', $q, $m)) foreach ($F as $f) if (strcasecmp(str_replace('-', '', (string) $f['file_number']), str_replace('-', '', $m[1])) === 0)
            return self::t("{$f['file_number']} ({$f['applicant']}, {$f['country']}): {$f['status']}.", "{$f['file_number']} ({$f['applicant']}, {$f['country']}): {$f['status']}।");
        $per = Slots::period($q);
        $rows = array_values(array_filter($F, fn($f) => self::in($f['submit_date'] ?? null, $per)));
        $st = []; foreach ($rows as $f) $st[(string) $f['status']] = ($st[(string) $f['status']] ?? 0) + 1; arsort($st);
        $parts = []; foreach ($st as $k => $c) $parts[] = "$k " . self::n($c);
        $when = self::when($per, 'in all', 'মোট');
        return self::t(sprintf('%s: %d contract files (%s).', ucfirst($when), count($rows), implode(', ', $parts)), sprintf('%s %sটা contract file (%s)।', $when, self::n(count($rows)), implode(', ', $parts)));
    }

    /* ---------- money in and out over a period ---------- */
    private static function cashflow(string $q, array $D): ?string
    {
        $per = Slots::period($q) ?? [date('Y-m-01'), date('Y-m-d'), 'this month', 'এই মাসে'];
        $in = 0.0; $out = 0.0;
        foreach ($D['party_transactions'] ?? [] as $p) { if (!self::in($p['date'], $per) || preg_match('/super ?admin/i', (string) $p['party_name'])) continue;
            if ($p['party_type'] === 'customer') $in += (float) $p['credit']; else $out += (float) $p['debit']; }
        foreach ($D['expenses'] ?? [] as $e) if (self::in($e['expense_date'], $per)) $out += (float) $e['amount'];
        foreach ($D['payments'] ?? [] as $p) if (self::in($p['date'], $per)) $out += (float) $p['amount'];
        $w = self::$bn ? Phrase::bnDigits($per[3]) : $per[2];
        return self::t(sprintf('%s: collected %s from agents and clients; paid out %s (vendors, expenses, salaries). Net %s.', ucfirst($w), self::m($in), self::m($out), self::m($in - $out)),
            sprintf('%s agent-client থেকে collection %s; বাইরে গেছে %s (vendor, খরচ, বেতন)। Net %s।', $w, self::m($in), self::m($out), self::m($in - $out)));
    }

    /* ---------- vouchers / journal entries over a period ---------- */
    private static function vouchers(string $q, array $D): ?string
    {
        $per = Slots::period($q) ?? [date('Y-m-d'), date('Y-m-d'), 'today', 'আজ'];
        $J = array_values(array_filter($D['journal_entries'] ?? [], fn($j) => self::in($j['date'], $per)));
        if (!$J) return self::t("No journal entries {$per[2]}.", Phrase::bnDigits($per[3]) . ' কোনো journal entry নেই।');
        $by = []; foreach ($J as $j) $by[(string) ($j['source'] ?: 'manual')] = ($by[(string) ($j['source'] ?: 'manual')] ?? 0) + 1; arsort($by);
        $parts = []; foreach (array_slice($by, 0, 5, true) as $k => $c) $parts[] = "$k " . self::n($c);
        $last = end($J);
        return self::t(sprintf('%d journal entries %s (%s). Latest: %s — %s.', count($J), $per[2], implode(', ', $parts), $last['reference'], $last['description']),
            sprintf('%s %sটা journal entry (%s)। সর্বশেষ: %s — %s।', Phrase::bnDigits($per[3]), self::n(count($J)), implode(', ', $parts), $last['reference'], $last['description']));
    }

    /* ---------- one ticket by PNR / ticket number ---------- */
    private static function ticketFind(string $q, array $D): ?string
    {
        if (!preg_match('/\b([A-Z0-9]{6}|\d{3}-?\d{10})\b/i', $q, $m)) return null;
        $key = strtoupper(str_replace('-', '', $m[1]));
        foreach ($D['ticket_index'] ?? [] as $t) if (strtoupper(str_replace('-', '', (string) $t['pnr'])) === $key) {
            // only what is on record — a ticket not yet invoiced has no client, invoice or sale to name
            $more = array_filter(['client' => $t['client'], 'vendor' => $t['vendor'], 'invoice' => $t['invoice'], 'sale' => (float) $t['sale_price'] > 0 ? self::m((float) $t['sale_price']) : null]);
            $tail = implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($more), $more));
            if (!$t['invoice']) $tail .= self::t($tail ? '; not invoiced yet' : 'not invoiced yet', $tail ? '; এখনো invoice হয়নি' : 'এখনো invoice হয়নি');
            return "{$t['pnr']}: {$t['passenger']}, {$t['route']}, {$t['status']}" . ($tail ? "; $tail" : '') . self::t('.', '।');
        }
        return self::t("No ticket {$m[1]} in the ERP.", "ERP-তে {$m[1]} নামে কোনো ticket নেই।");
    }

    /* ---------- leads ---------- */
    private static function leads(string $q, array $D): ?string
    {
        $L = $D['leads'] ?? []; $per = Slots::period($q);
        $rows = array_values(array_filter($L, fn($l) => self::in($l['created_at'] ?? null, $per)));
        $st = []; foreach ($rows as $l) $st[(string) ($l['status'] ?: '—')] = ($st[(string) ($l['status'] ?: '—')] ?? 0) + 1;
        $parts = []; foreach ($st as $k => $c) $parts[] = "$k " . self::n($c);
        $when = self::when($per, 'in all', 'মোট');
        return self::t(sprintf('%d leads %s%s.', count($rows), $when, $parts ? ' (' . implode(', ', $parts) . ')' : ''), sprintf('%s %sটা lead%s।', $when, self::n(count($rows)), $parts ? ' (' . implode(', ', $parts) . ')' : ''));
    }

    /* ---------- projects: running, late, over budget ---------- */
    private static function projects(string $q, array $D): ?string
    {
        $P = $D['projects'] ?? []; if (!$P) return null; $co = Slots::company($q, $D);
        $P = array_values(array_filter($P, fn($p) => !$co || (int) $p['company_id'] === (int) $co['id']));
        $run = array_filter($P, fn($p) => !in_array(strtolower((string) $p['status']), ['completed', 'cancelled', 'done'], true));
        $over = array_filter($P, fn($p) => (float) $p['budget'] > 0 && (float) $p['spent'] > (float) $p['budget']);
        $late = array_filter($run, fn($p) => $p['end_date'] && $p['end_date'] < date('Y-m-d'));
        $names = fn($xs) => implode(', ', array_map(fn($p) => $p['project_name'], array_slice(array_values($xs), 0, 4)));
        return self::t(sprintf('%d projects running%s. Over budget: %d%s. Past their end date: %d%s.', count($run), $co ? " at {$co['name']}" : '', count($over), $over ? ' (' . $names($over) . ')' : '', count($late), $late ? ' (' . $names($late) . ')' : ''),
            sprintf('%sচলমান project %sটা। বাজেট ছাড়িয়েছে %sটা%s; সময় পার %sটা%s।', $co ? "{$co['name']}-এ " : '', self::n(count($run)), self::n(count($over)), $over ? ' (' . $names($over) . ')' : '', self::n(count($late)), $late ? ' (' . $names($late) . ')' : ''));
    }

    /* ---------- contract flights: revenue against cost ---------- */
    private static function flightProfit(string $q, array $D): ?string
    {
        $per = Slots::period($q); $when = self::when($per, 'on record', 'রেকর্ডের সব');
        $F = array_values(array_filter($D['contract_flights'] ?? [], fn($f) => self::in($f['departure_at'], $per)));
        if (preg_match('/\b([a-z]{2,3}-?\d{2,4})\b/i', $q, $m)) { $c = strtoupper(str_replace('-', '', $m[1])); $F = array_values(array_filter($F, fn($f) => str_replace('-', '', strtoupper((string) $f['flight_number'])) === $c)); }
        if (!$F) return self::t("No contract flights $when.", "$when কোনো contract flight নেই।");
        $rev = array_sum(array_map(fn($f) => (float) $f['revenue'], $F)); $cost = array_sum(array_map(fn($f) => (float) $f['cost_price'], $F));
        usort($F, fn($a, $b) => ((float) $b['revenue'] - (float) $b['cost_price']) <=> ((float) $a['revenue'] - (float) $a['cost_price']));
        $parts = array_map(fn($f) => $f['flight_number'] . ' ' . self::m((float) $f['revenue'] - (float) $f['cost_price']), array_slice($F, 0, 5));
        $route = preg_match('/[\p{L}]+\s*-\s*[\p{L}]+/u', $q) ? self::t(' (the ERP keeps no route on contract flights, so this is all of them)', ' (contract flight-এ ERP route রাখে না, তাই সবগুলো মিলিয়ে)') : '';
        return self::t(sprintf('Contract flights %s: revenue %s, cost %s, profit %s across %d flights%s. By flight: %s.', $when, self::m($rev), self::m($cost), self::m($rev - $cost), count($F), $route, implode('; ', $parts)),
            sprintf('%s contract flight: আয় %s, খরচ %s, লাভ %s — %sটা flight%s। flight অনুযায়ী: %s।', $when, self::m($rev), self::m($cost), self::m($rev - $cost), self::n(count($F)), $route, implode('; ', $parts)));
    }

    /* ---------- ticketing: what we made on tickets ---------- */
    private static function ticketProfit(string $q, array $D): ?string
    {
        $per = Slots::period($q) ?? [date('Y-m-01'), date('Y-m-d'), 'this month', 'এই মাসে'];
        $cost = []; $vendor = []; foreach ($D['ticket_purchases'] ?? [] as $x) { $cost[$x['id']] = (float) ($x['total'] ?? 0); $vendor[$x['id']] = (string) ($x['airline'] ?: $x['vendor']); }
        [$p] = self::partyOrSay($q, $D, ['supplier']);
        $n = 0; $sale = 0.0; $c = 0.0;
        foreach ($D['ticket_index'] ?? [] as $t) { if (!self::in($t['date'], $per) || in_array($t['status'], ['cancelled', 'void'], true) || (float) $t['sale_price'] <= 0) continue;
            if ($p && (string) $t['vendor'] !== $p['name']) continue;
            $n++; $sale += (float) $t['sale_price']; $c += $cost[$t['purchase_id']] ?? 0; }
        $w = self::$bn ? Phrase::bnDigits($per[3]) : $per[2]; $who = $p ? $p['name'] . ' ' : '';
        if (!$n) return self::t("No invoiced tickets {$who}{$per[2]}.", "$w {$who}কোনো invoice-করা ticket নেই।");
        return self::t(sprintf('Tickets %s%s: %d sold for %s against %s cost — %s made (%s margin). Airline commission and ADMs are not in the ERP data.', $who, $per[2], $n, self::m($sale), self::m($c), self::m($sale - $c), round(100 * ($sale - $c) / max(1, $sale), 1) . '%'),
            sprintf('%s %sticket: %sটা বিক্রি %s, খরচ %s — লাভ %s (margin %s%%)। airline commission আর ADM ERP-র data-তে নেই।', $w, $who, self::n($n), self::m($sale), self::m($c), self::m($sale - $c), self::n(round(100 * ($sale - $c) / max(1, $sale), 1))));
    }

    /* ---------- the travel business at a glance ---------- */
    private static function travelSummary(string $q, array $D): ?string
    {
        $per = Slots::period($q) ?? [date('Y-m-01'), date('Y-m-d'), 'this month', 'এই মাসে'];
        $tk = array_filter($D['ticket_index'] ?? [], fn($t) => self::in($t['date'], $per));
        $can = count(array_filter($tk, fn($t) => in_array($t['status'], ['cancelled', 'void'], true)));
        $ts = array_filter($D['ticket_sales'] ?? [], fn($s) => self::in($s['date'], $per));
        $fl = array_filter($D['contract_flights'] ?? [], fn($f) => self::in($f['departure_at'], $per));
        $vs = array_filter($D['visa_sales'] ?? [], fn($v) => self::in($v['date'], $per));
        $sales = array_sum(array_map(fn($s) => (float) $s['total'], $ts)); $due = array_sum(array_map(fn($s) => (float) $s['due_amount'], $ts));
        $w = self::$bn ? Phrase::bnDigits($per[3]) : $per[2];
        return self::t(sprintf('Travel, %s: %d tickets (%d cancelled/void), ticket invoices %s of which %s still due; %d contract flights; %d visa invoices worth %s.', $per[2], count($tk), $can, self::m($sales), self::m($due), count($fl), count($vs), self::m(array_sum(array_map(fn($v) => (float) $v['total'], $vs)))),
            sprintf('ট্রাভেল, %s: %sটা ticket (%sটা cancel/void), ticket invoice %s — তার %s এখনো due; %sটা contract flight; %sটা visa invoice, %s।', $w, self::n(count($tk)), self::n($can), self::m($sales), self::m($due), self::n(count($fl)), self::n(count($vs)), self::m(array_sum(array_map(fn($v) => (float) $v['total'], $vs)))));
    }

    /* ---------- where money was lost ---------- */
    private static function losses(string $q, array $D): ?string
    {
        $per = Slots::period($q) ?? [date('Y-m-01'), date('Y-m-d'), 'this month', 'এই মাসে'];
        $type = []; foreach ($D['accounts'] ?? [] as $a) $type[$a['id']] = strtolower((string) $a['type']);
        $name = []; foreach ($D['companies'] ?? [] as $c) $name[$c['id']] = (string) $c['name'];
        $pl = [];
        foreach ($D['journal_entries'] ?? [] as $j) { if (!self::in($j['date'], $per)) continue; $k = $name[$j['company_id']] ?? 'Group';
            foreach ((array) ($j['items'] ?? []) as $it) { $ty = $type[$it['account_id'] ?? 0] ?? '';
                if (str_contains($ty, 'income') || str_contains($ty, 'revenue')) $pl[$k] = ($pl[$k] ?? 0) + (float) $it['credit'] - (float) $it['debit'];
                elseif (str_contains($ty, 'expense') || str_contains($ty, 'cost')) $pl[$k] = ($pl[$k] ?? 0) - (float) $it['debit'] + (float) $it['credit']; } }
        $loss = array_filter($pl, fn($v) => $v < 0); asort($loss);
        $cost = []; foreach ($D['ticket_purchases'] ?? [] as $x) $cost[$x['id']] = (float) ($x['total'] ?? 0);
        $neg = []; foreach ($D['ticket_index'] ?? [] as $t) if (self::in($t['date'], $per) && (float) $t['sale_price'] > 0 && $t['client'] && (float) $t['sale_price'] < ($cost[$t['purchase_id']] ?? 0)) $neg[(string) $t['client']] = ($neg[(string) $t['client']] ?? 0) + (float) $t['sale_price'] - $cost[$t['purchase_id']];
        asort($neg);
        $w = self::$bn ? Phrase::bnDigits($per[3]) : $per[2];
        $a = $loss ? implode('; ', array_map(fn($k, $v) => "$k " . self::m($v), array_keys($loss), $loss)) : self::t('no section ran at a loss', 'কোনো সেকশনে লস নেই');
        $b = $neg ? implode('; ', array_map(fn($k, $v) => "$k " . self::m($v), array_keys(array_slice($neg, 0, 4, true)), array_slice($neg, 0, 4, true))) : '';
        // braces: a Bangla danda right after a variable is read by PHP as part of its name
        return self::t("Losses, {$per[2]}: by section — {$a}." . ($b ? " Tickets sold below cost — {$b}." : ''), "{$w} লস: সেকশন অনুযায়ী — {$a}।" . ($b ? " cost-এর নিচে বিক্রি হওয়া ticket — {$b}।" : ''));
    }

    /* ---------- ticket changes: what reschedules / reissues / refunds moved ---------- */
    private static function changes(string $q, array $D): ?string
    {
        if (!isset($D['ticket_changes'])) return null;   // an older server: let the next layer answer
        $per = Slots::period($q); $when = self::when($per, 'on record', 'রেকর্ডের সব');
        [$p, $miss] = self::partyOrSay($q, $D, ['customer']); if ($miss) return $miss;
        $l = mb_strtolower($q);
        $kind = preg_match('/রিইস্যু|reissue/u', $l) ? 'reissue' : (preg_match('/রিফান্ড|refund/u', $l) ? 'refund' : (preg_match('/ভয়েড|void/u', $l) ? 'void' : (preg_match('/রিশিডিউল|reschedule|ডেট চেঞ্জ|date change/u', $l) ? 'reschedule' : null)));
        $C = array_values(array_filter($D['ticket_changes'], fn($c) => self::in($c['created_at'], $per) && (!$kind || $c['kind'] === $kind) && (!$p || (string) $c['client'] === $p['name'])));
        $label = $kind ?? self::t('ticket change', 'ticket change'); $who = $p ? $p['name'] . ' ' : '';
        if (!$C) return self::t("No {$label}s {$who}{$when}.", "{$when} {$who}কোনো {$label} নেই।");
        $pen = array_sum(array_map(fn($c) => (float) $c['penalty'], $C)); $fd = array_sum(array_map(fn($c) => (float) $c['fare_difference'], $C)); $fee = array_sum(array_map(fn($c) => (float) $c['service_fee'], $C)); $ref = array_sum(array_map(fn($c) => (float) $c['refund_amount'], $C));
        return self::t(sprintf('%s%ss %s: %d — penalty %s, fare difference %s, our service fee %s%s.', $who, $label, $when, count($C), self::m($pen), self::m($fd), self::m($fee), $ref > 0 ? ', refunded ' . self::m($ref) : ''),
            sprintf('%s %s%sটা %s — penalty %s, fare difference %s, আমাদের service fee %s%s।', $when, $who ? $who . '— ' : '', self::n(count($C)), $label, self::m($pen), self::m($fd), self::m($fee), $ref > 0 ? ', refund ' . self::m($ref) : ''));
    }

    /* ---------- banks: one or all ---------- */
    private static function banks(string $q, array $D): ?string
    {
        if (class_exists('Ops') && ($o = Ops::answer($q, $D, self::$bn ? 'bn' : 'en')) && str_starts_with($o['intent'], 'ops_bank')) return $o['text'];
        $B = $D['banks'] ?? []; if (!$B) return null;
        usort($B, fn($a, $b) => (float) $b['balance'] <=> (float) $a['balance']);
        $tot = array_sum(array_map(fn($b) => (float) $b['balance'], $B));
        $parts = array_map(fn($b) => $b['name'] . ' ' . self::m((float) $b['balance']), array_slice($B, 0, 6));
        return self::t('All banks and wallets: ' . self::m($tot) . '. Largest: ' . implode('; ', $parts) . '.', 'সব ব্যাংক-ওয়ালেট মিলিয়ে ' . self::m($tot) . '। বড়গুলো: ' . implode('; ', $parts) . '।');
    }
}
