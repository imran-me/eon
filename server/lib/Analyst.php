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
    public static function handle(string $intent, string $q, array $D, bool $bn): ?array
    {
        self::$bn = $bn;
        if (in_array($intent, self::ACTIONS, true)) return ['client_command' => $q];
        $map = [
            'এজেন্টভিত্তিক লাভ' => 'agentProfit', 'এজেন্ট অ্যানালিটিক্স' => 'agentProfit',
            'ফ্লাইট কাউন্ট - এজেন্টভিত্তিক' => 'agentFlights',
            'খরচ জানা' => 'expenses', 'ক্যাশ/ব্যাংক' => null,
            'সেকশনভিত্তিক লাভ' => 'sectionProfit', 'দৈনিক/সময়ভিত্তিক লাভ' => 'sectionProfit', 'লাভজনকতা তুলনা' => 'sectionProfit', 'সেকশন তুলনা ও র‍্যাংকিং' => 'sectionProfit', 'ট্রেন্ড ও তুলনা' => 'sectionProfit',
            'এজেন্ট ব্যালেন্স/ডিউ' => 'agentDue', 'ডিউ/বকেয়া' => 'agentDue', 'ট্রাভেল অ্যাকাউন্টস' => 'agentDue',
            'ভেন্ডর পেয়েবল' => 'vendor', 'ভেন্ডরভিত্তিক খরচ/লাভ' => 'vendor',
            'টিকেট কাউন্ট/লিস্ট' => 'tickets',
            'ফ্লাইট লিস্ট - এয়ারলাইন/রুট' => 'flights', 'ফ্লাইট লিস্ট - সময়ভিত্তিক' => 'flights', 'ক্যান্সেলড ফ্লাইট কাউন্ট' => 'flights', 'ফ্লাইট ওপেন/খোঁজা' => 'flights', 'সিট ইনভেন্টরি' => 'flights',
            'ব্যাংক ব্যালেন্স' => 'banks',
        ];
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
