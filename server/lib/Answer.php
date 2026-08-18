<?php
declare(strict_types=1);

/* ============================================================
   Answer — the offline brain's mouth.

   Takes what Nlu understood, pulls the real numbers out of
   Analytics/Insight/Kb, and says it the way a person would:
   the number first, then why it is that number, then the one
   thing worth doing about it.

   Every string exists twice, written natively in each language.
   Nothing here is translated from the other side.
   ============================================================ */
final class Answer
{
    private array $c = [];          // lang, slots, A, I, D, tools, used

    public static function compose(string $question, array $parse, Analytics $A, Tools $tools): array
    {
        $self = new self();
        $self->c = [
            'lang'  => $parse['lang'],
            'slots' => $parse['slots'],
            'norm'  => $parse['normalised'],
            'q'     => $question,
            'A'     => $A,
            'I'     => new Insight($A),
            'D'     => $A->dataset(),
            'tools' => $tools,
            'used'  => [],
        ];
        Phrase::seed($parse['normalised']);

        $intent = $parse['intent'];
        $method = $intent !== null ? 'a_' . $intent : null;
        $text = null;

        if ($method !== null && method_exists($self, $method)) {
            $text = $self->$method();
        }
        if ($text === null || $text === '') {
            $text = $self->fallback($parse);
        }

        return ['text' => $text, 'intent' => $intent, 'lang' => $parse['lang'],
                'tools_used' => array_values(array_unique($self->c['used']))];
    }

    /* ---------------- small helpers ---------------- */

    private function bn(): bool { return $this->c['lang'] === 'bn'; }
    private function L(): string { return $this->c['lang']; }
    private function A(): Analytics { return $this->c['A']; }
    private function I(): Insight { return $this->c['I']; }
    private function used(string $t): void { $this->c['used'][] = $t; }
    private function m(float $v): string { return Phrase::money($v, $this->L()); }
    private function num($v): string { return Phrase::n($v, $this->L()); }
    private function pc(float $v): string { return Phrase::pct($v, $this->L()); }
    private function f($v): float { return (float) ($v ?? 0); }

    /** pick the English or Bangla member of a pair */
    private function t(string $en, string $bn): string { return $this->bn() ? $bn : $en; }

    private function say(array $parts): string { return Phrase::sentence($parts); }

    private function open(string $mood, string $salt = ''): string { return Phrase::opener($mood, $this->L(), $salt); }
    private function act(string $en, string $bn): string { return Phrase::advise($this->t($en, $bn), $this->L()); }

    /** which month is the boss asking about? */
    private function monthKey(): string
    {
        $s = $this->c['slots'];
        $today = $this->A()->today();
        if ($s['month']) {
            $y = $s['year'] ?: (int) substr($today, 0, 4);
            return sprintf('%04d-%02d', $y, $s['month']);
        }
        if ($s['period'] === 'last_month') return date('Y-m', strtotime('-1 month', strtotime(substr($today, 0, 7) . '-01')));
        return substr($today, 0, 7);
    }

    /* ================= EXECUTIVE ================= */

    private function a_brief(): string
    {
        $this->used('get_brief');
        $A = $this->A();
        $k = $A->kpis();
        $ap = $A->approvals();
        $td = $A->attendanceToday();
        $rw = $this->I()->runway();

        $cash = $this->f($k['cash'] ?? 0);
        $arOv = $this->f($k['receivables_overdue'] ?? 0);
        $apOv = $this->f($k['payables_overdue'] ?? 0);
        $mood = ($apOv > $cash) ? 'bad' : ($arOv > 0 || $apOv > 0 ? 'warn' : 'ok');

        $noPunch = ((int) ($td['present'] ?? 0) === 0 && (int) ($td['absent'] ?? 0) === 0 && (int) ($td['late'] ?? 0) === 0);
        $att = ($td['weekend'] ?? false)
            ? $this->t('It is the weekend, so no attendance today.',
                       'আজ সাপ্তাহিক ছুটি, তাই হাজিরার হিসাব নেই।')
            : ($noPunch
            ? $this->t('Attendance has not come through for today yet.', 'আজকের হাজিরা এখনো ওঠেনি।')
            : $this->t(sprintf('%s of %s are in today%s.', $this->num($td['present'] ?? 0), $this->num($td['total'] ?? 0),
                        ($td['absent'] ?? 0) ? ', ' . $this->num($td['absent']) . ' absent' : ''),
                       sprintf('আজ %s জনের মধ্যে %s জন এসেছে%s।', $this->num($td['total'] ?? 0), $this->num($td['present'] ?? 0),
                        ($td['absent'] ?? 0) ? ', ' . $this->num($td['absent']) . ' জন আসেনি' : '')));

        $money = $this->t(
            sprintf('Cash stands at %s.%s%s', $this->m($cash),
                $apOv > 0 ? ' ' . $this->m($apOv) . ' of payments is already past its date.' : '',
                $arOv > 0 ? ' ' . $this->m($arOv) . ' is overdue coming in.' : ''),
            sprintf('হাতে আছে %s।%s%s', $this->m($cash),
                $apOv > 0 ? ' ' . $this->m($apOv) . ' পেমেন্টের তারিখ পেরিয়ে গেছে।' : '',
                $arOv > 0 ? ' ' . $this->m($arOv) . ' পাওনা আটকে আছে।' : ''));

        $queue = ($ap['count'] ?? 0)
            ? $this->t(sprintf('%s %s waiting on your approval, worth %s.', $this->num($ap['count']),
                        Phrase::plural((int) $ap['count'], 'item is', 'items are'), $this->m($this->f($ap['amount'] ?? 0))),
                       sprintf('আপনার অনুমোদনের অপেক্ষায় %s টা বিষয়, মোট %s।', $this->num($ap['count']), $this->m($this->f($ap['amount'] ?? 0))))
            : $this->t('Your approval queue is clear.', 'অনুমোদনের সারি খালি।');

        $runway = ($rw['months_covered'] !== null && $rw['months_covered'] < 2)
            ? $this->t(sprintf('One thing I would not leave alone: at the current burn of %s a month, that cash covers about %s months.',
                        $this->m($this->f($rw['monthly_burn'])), $this->num($rw['months_covered'])),
                       sprintf('একটা জিনিস ফেলে রাখবেন না: মাসে %s খরচ হচ্ছে, ওই হিসাবে হাতের টাকায় চলবে প্রায় %s মাস।',
                        $this->m($this->f($rw['monthly_burn'])), $this->num($rw['months_covered'])))
            : '';

        $advice = ($ap['count'] ?? 0)
            ? $this->act('clear the approval queue first — everything else waits behind it.',
                         'আগে অনুমোদনের সারিটা খালি করুন — বাকি সব ওটার পেছনে আটকে আছে।')
            : ($apOv > 0
                ? $this->act('settle the overdue payments before they turn into calls.',
                             'যেগুলোর তারিখ পেরিয়েছে সেগুলো আগে মিটিয়ে ফেলুন, নইলে ফোন আসা শুরু হবে।')
                : $this->act('nothing is on fire — spend the day on the pipeline.',
                             'জরুরি কিছু নেই — আজকের দিনটা পাইপলাইনে দিন।'));

        return $this->say([$this->open($mood), $money, $queue, $att, $runway, $advice]);
    }

    private function a_focus(): string
    {
        $this->used('get_decisions');
        $dec = $this->A()->decisions();
        if (!$dec) {
            return $this->say([$this->open('good'),
                $this->t('Nothing is demanding you right now. The queue is clear and nothing has crossed a threshold.',
                         'এই মুহূর্তে কিছুই আপনাকে টানছে না। সারি খালি, কোনো সীমাও পার হয়নি।'),
                $this->act('a good day to look forward rather than back.',
                           'আজকের দিনটা পেছনে না তাকিয়ে সামনে তাকানোর জন্য ভালো।')]);
        }

        $first = $dec[0];
        $title = Loc::bn((string) ($first['title'] ?? ''), $this->L());
        $why = $first['why'] ?? ($first['detail'] ?? '');
        if (is_array($why)) $why = Phrase::join(Loc::bnAll(array_map('strval', $why), $this->L()), $this->L());
        else $why = Loc::bn((string) $why, $this->L());
        $rec = Loc::bn((string) ($first['recommend'] ?? ''), $this->L());

        $critical = 0;
        foreach ($dec as $d) if ((int) ($d['severity'] ?? 0) >= 5) $critical++;
        $mood = $critical > 0 ? 'bad' : 'warn';

        $rest = array_slice($dec, 1, 2);
        $restText = '';
        if ($rest) {
            $titles = array_map(fn($d) => Loc::bn((string) ($d['title'] ?? ''), $this->L()), $rest);
            $restText = $this->t('After that: ' . Phrase::join($titles, 'en') . '.',
                                 'তারপর: ' . Phrase::join($titles, 'bn') . '।');
        }

        return $this->say([
            $this->open($mood),
            $this->t(sprintf('%s things want you%s.', $this->num(count($dec)),
                        $critical ? ', ' . $this->num($critical) . ' of them critical' : ''),
                     sprintf('%s টা বিষয় আপনার নজর চাইছে%s।', $this->num(count($dec)),
                        $critical ? ', তার ' . $this->num($critical) . ' টা জরুরি' : '')),
            $this->t('Start with: ', 'শুরু করুন: ') . $title . $this->t('.', '।'),
            $why !== '' ? $this->t('Because ' . $why . '.', 'কারণ ' . $why . '।') : '',
            $restText,
            $rec !== '' ? Phrase::advise($rec, $this->L()) : '',
        ]);
    }

    private function a_approvals(): string
    {
        $this->used('get_approvals');
        $r = $this->A()->approvals();
        $n = (int) ($r['count'] ?? 0);
        if ($n === 0) {
            return $this->say([$this->open('good'),
                $this->t('Your approval queue is empty — nothing is sitting on your desk.',
                         'অনুমোদনের সারি খালি — আপনার টেবিলে কিছু পড়ে নেই।')]);
        }
        $items = $r['items'] ?? [];
        $top = $items[0] ?? null;
        $mood = $n >= 5 ? 'bad' : 'warn';
        $breakdown = [];
        foreach ($items as $it) {
            $k = (string) ($it['kind'] ?? 'item');
            $breakdown[$k] = ($breakdown[$k] ?? 0) + 1;
        }
        $bits = [];
        foreach ($breakdown as $k => $v) $bits[] = $this->num($v) . ' ' . str_replace('_', ' ', $k);

        return $this->say([
            $this->open($mood),
            $this->t(sprintf('%s %s waiting on you, %s in total — %s.', $this->num($n),
                      Phrase::plural($n, 'item is', 'items are'), $this->m($this->f($r['amount'] ?? 0)), Phrase::join($bits, 'en')),
                     sprintf('%s টা বিষয় আপনার অনুমোদনের অপেক্ষায়, মোট %s — %s।', $this->num($n),
                      $this->m($this->f($r['amount'] ?? 0)), Phrase::join($bits, 'bn'))),
            $top ? $this->t('The biggest is ' . $top['title'] . ($this->f($top['amount'] ?? 0) ? ' at ' . $this->m($this->f($top['amount'])) : '') . '.',
                            'সবচেয়ে বড়টা ' . $top['title'] . ($this->f($top['amount'] ?? 0) ? ', ' . $this->m($this->f($top['amount'])) : '') . '।') : '',
            $this->act('open Payment Schedules and clear the largest three — that is most of the value.',
                       'পেমেন্ট সূচিতে গিয়ে বড় তিনটা ছেড়ে দিন — টাকার দিক থেকে ওটাই বেশিরভাগ।'),
        ]);
    }

    private function a_risks(): string
    {
        $this->used('get_decisions');
        $this->used('get_kpis');
        $A = $this->A();
        $I = $this->I();
        $k = $A->kpis();
        $rw = $I->runway();
        $an = $I->anomalies();
        $ap = $A->approvals();

        $points = [];
        if ($rw['months_covered'] !== null && $rw['months_covered'] < 2) {
            $points[] = $this->t(
                sprintf('cash covers about %s months at the current burn (%s a month, %s%% of it payroll)',
                    $this->num($rw['months_covered']), $this->m($this->f($rw['monthly_burn'])), $this->num((int) $rw['payroll_share'])),
                sprintf('হাতের টাকায় চলবে প্রায় %s মাস — মাসে খরচ %s, তার %s%% ই বেতন',
                    $this->num($rw['months_covered']), $this->m($this->f($rw['monthly_burn'])), $this->num((int) $rw['payroll_share'])));
        }
        if ($this->f($k['payables_overdue'] ?? 0) > 0) {
            $points[] = $this->t(sprintf('%s of payments is past its date', $this->m($this->f($k['payables_overdue']))),
                                 sprintf('%s পেমেন্টের তারিখ পেরিয়ে গেছে', $this->m($this->f($k['payables_overdue']))));
        }
        if (($ap['count'] ?? 0) >= 3) {
            $points[] = $this->t(sprintf('%s approvals are stacked up behind you', $this->num($ap['count'])),
                                 sprintf('%s টা অনুমোদন আপনার পেছনে জমে আছে', $this->num($ap['count'])));
        }
        if (($an['count'] ?? 0) > 0) {
            $points[] = $this->t(sprintf('%s things in the books look irregular', $this->num($an['count'])),
                                 sprintf('হিসাবে %s টা জিনিস অস্বাভাবিক দেখাচ্ছে', $this->num($an['count'])));
        }

        if (!$points) {
            return $this->say([$this->open('good'),
                $this->t('I checked cash cover, overdue payments, the approval queue and the books — nothing crosses a line today.',
                         'টাকার কভার, বকেয়া পেমেন্ট, অনুমোদনের সারি আর হিসাব — সব দেখলাম, আজ কোনোটাই সীমা ছাড়ায়নি।'),
                $this->act('the blind spot that remains is what the ERP does not print: aging and cash-flow. Ask me for those directly.',
                           'যেটা এখনো অন্ধ জায়গা সেটা ERP ছাপায় না: aging আর নগদ প্রবাহ। ওগুলো আমাকে সরাসরি জিজ্ঞেস করুন।')]);
        }

        return $this->say([
            $this->open('bad'),
            $this->t(sprintf('%s %s I would not leave alone —', $this->num(count($points)), Phrase::plural(count($points), 'thing', 'things')),
                     sprintf('%s টা জিনিস আমি ফেলে রাখতাম না —', $this->num(count($points)))),
            Phrase::join($points, $this->L()) . $this->t('.', '।'),
            $this->act('take the first one today; the rest can hold a day.',
                       'প্রথমটা আজই ধরুন; বাকিগুলো একদিন অপেক্ষা করতে পারে।'),
        ]);
    }

    private function a_anomalies(): string
    {
        $this->used('get_anomalies');
        $an = $this->I()->anomalies();
        if (($an['count'] ?? 0) === 0) {
            return $this->say([$this->open('good'),
                $this->t('I looked for duplicate expenses, spending spikes against each category\'s own average, approvals gone stale, negative balances and a trial balance that does not tie. Nothing came up.',
                         'ডুপ্লিকেট খরচ, প্রতিটা খাতের নিজের গড়ের তুলনায় হঠাৎ বেশি খরচ, পড়ে থাকা অনুমোদন, ঋণাত্মক ব্যালেন্স আর না-মেলা রেওয়ামিল — সব খুঁজে দেখলাম। কিছু পাইনি।')]);
        }
        $lines = [];
        foreach (array_slice($an['items'], 0, 4) as $x) {
            $lines[] = $this->anomalyLine($x);
        }
        return $this->say([
            $this->open('bad'),
            $this->t(sprintf('%s %s look wrong.', $this->num($an['count']), Phrase::plural((int) $an['count'], 'thing', 'things')),
                     sprintf('%s টা জিনিস ঠিক লাগছে না।', $this->num($an['count']))),
            implode($this->t(' ', ' '), $lines),
            $this->act('start with anything marked duplicate — that is money already out of the door twice.',
                       'ডুপ্লিকেট চিহ্নিত যেগুলো, ওগুলো দিয়ে শুরু করুন — ওই টাকা দুইবার বেরিয়ে গেছে।'),
        ]);
    }

    private function anomalyLine(array $x): string
    {
        $kind = (string) ($x['kind'] ?? '');
        switch ($kind) {
            case 'duplicate_expense':
                return $this->t(sprintf('"%s" for %s was booked twice on %s.', $x['title'], $this->m($this->f($x['amount'])), Phrase::day((string) $x['date'], 'en')),
                                sprintf('"%s" — %s, %s তারিখে দুইবার বসানো হয়েছে।', $x['title'], $this->m($this->f($x['amount'])), Phrase::day((string) $x['date'], 'bn')));
            case 'expense_spike':
                return $this->t(sprintf('%s is running %s times its usual month at %s.', $x['category'], $this->num($x['times']), $this->m($this->f($x['amount']))),
                                sprintf('%s খাতে খরচ স্বাভাবিকের %s গুণ — %s।', $x['category'], $this->num($x['times']), $this->m($this->f($x['amount']))));
            case 'stale_approval':
                return $this->t(sprintf('"%s" has been waiting %s days for you.', $x['title'], $this->num($x['days'])),
                                sprintf('"%s" %s দিন ধরে আপনার জন্য পড়ে আছে।', $x['title'], $this->num($x['days'])));
            case 'negative_balance':
                return $this->t(sprintf('%s is showing a negative balance of %s.', $x['title'], $this->m($this->f($x['amount']))),
                                sprintf('%s এ ঋণাত্মক ব্যালেন্স দেখাচ্ছে — %s।', $x['title'], $this->m($this->f($x['amount']))));
            case 'trial_balance_off':
                return $this->t(sprintf('The trial balance is out by %s — that is almost always a shared account tagged to one company.', $this->m(abs($this->f($x['amount'])))),
                                sprintf('রেওয়ামিল %s গরমিল — এটা প্রায় সবসময়ই শেয়ার্ড হিসাব এক কোম্পানিতে ট্যাগ হওয়ার কারণে হয়।', $this->m(abs($this->f($x['amount'])))));
            case 'payslip_math':
                return $this->t(sprintf('%s\'s %s payslip does not add up: gross %s less %s deductions should not net %s.',
                                    $x['title'], $x['month'], $this->m($this->f($x['gross'])), $this->m($this->f($x['deductions'])), $this->m($this->f($x['net']))),
                                sprintf('%s এর %s মাসের স্লিপ মিলছে না: গ্রস %s থেকে %s কর্তন হলে নিট %s হওয়ার কথা না।',
                                    $x['title'], $x['month'], $this->m($this->f($x['gross'])), $this->m($this->f($x['deductions'])), $this->m($this->f($x['net']))));
        }
        return (string) ($x['title'] ?? $kind);
    }

    private function a_health(): string
    {
        $this->used('get_kpis');
        $A = $this->A();
        $I = $this->I();
        $k = $A->kpis();
        $rw = $I->runway();
        $td = $A->attendanceToday();
        $tk = $A->tasks();

        $scores = [];
        $scores['cash'] = $rw['months_covered'] === null ? 60 : min(100, (int) round(min(6.0, (float) $rw['months_covered']) / 6 * 100));
        $ap = $this->f($k['payables_overdue'] ?? 0);
        $cash = $this->f($k['cash'] ?? 0);
        $scores['payments'] = $ap <= 0 ? 100 : (int) max(0, round(100 - min(100, $ap / max(1.0, $cash) * 100)));
        $scores['people'] = ($td['weekend'] ?? false) ? 100 : (int) round($this->f($td['present_pct'] ?? 0));
        $open = max(1, (int) ($tk['open'] ?? 0));
        $scores['delivery'] = (int) max(0, round(100 - min(100, ($tk['overdue'] ?? 0) / $open * 100)));

        $overall = (int) round(array_sum($scores) / count($scores));
        $mood = Phrase::moodHigh((float) $overall, 75, 55);
        $weakest = array_keys($scores, min($scores))[0];
        $labels = ['cash' => ['cash cover', 'টাকার কভার'], 'payments' => ['payment discipline', 'পেমেন্টের শৃঙ্খলা'],
                   'people' => ['attendance', 'হাজিরা'], 'delivery' => ['delivery', 'কাজের ডেলিভারি']];

        $parts = [];
        foreach ($scores as $key => $v) {
            $parts[] = $this->t($labels[$key][0], $labels[$key][1]) . ' ' . $this->num($v);
        }

        return $this->say([
            $this->open($mood),
            $this->t(sprintf('Overall health is %s out of 100.', $this->num($overall)),
                     sprintf('সার্বিক স্কোর ১০০ তে %s।', $this->num($overall))),
            $this->t('Behind it: ', 'ভেতরের হিসাব: ') . Phrase::join($parts, $this->L()) . $this->t('.', '।'),
            $this->t(sprintf('The weak leg is %s.', $labels[$weakest][0]), sprintf('দুর্বল দিকটা হলো %s।', $labels[$weakest][1])),
            $this->act('lift the weakest leg — the others are already carrying their share.',
                       'দুর্বল দিকটা আগে তুলুন — বাকিগুলো এমনিতেই নিজের ভাগ টানছে।'),
        ]);
    }

    private function a_since(): string
    {
        $this->used('get_kpis');
        $A = $this->A();
        $today = $A->today();
        $y = date('Y-m-d', strtotime('-1 day', strtotime($today)));
        $newExp = $newJe = 0;
        $expAmt = 0.0;
        foreach ($A->rows('expenses') as $e) {
            if (substr((string) ($e['expense_date'] ?? ''), 0, 10) >= $y) { $newExp++; $expAmt += $this->f($e['amount'] ?? 0); }
        }
        foreach ($A->rows('journal_entries') as $j) {
            if (substr((string) ($j['date'] ?? ''), 0, 10) >= $y) $newJe++;
        }
        $ap = $A->approvals();

        if ($newExp === 0 && $newJe === 0) {
            return $this->say([$this->open('ok'),
                $this->t('Nothing new has been booked since yesterday — no expenses, no journal entries.',
                         'গতকাল থেকে নতুন কিছু বসেনি — খরচও না, জার্নাল এন্ট্রিও না।'),
                ($ap['count'] ?? 0) ? $this->t($this->num($ap['count']) . ' approvals are still waiting from before.',
                                               'আগের ' . $this->num($ap['count']) . ' টা অনুমোদন এখনো পড়ে আছে।') : '']);
        }
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('Since yesterday: %s new %s worth %s, and %s journal %s posted.',
                        $this->num($newExp), Phrase::plural($newExp, 'expense', 'expenses'), $this->m($expAmt),
                        $this->num($newJe), Phrase::plural($newJe, 'entry', 'entries')),
                     sprintf('গতকাল থেকে: নতুন %s টা খরচ, মোট %s, আর %s টা জার্নাল এন্ট্রি বসেছে।',
                        $this->num($newExp), $this->m($expAmt), $this->num($newJe))),
            ($ap['count'] ?? 0) ? $this->t($this->num($ap['count']) . ' items still sit in your approval queue.',
                                           'আপনার সারিতে এখনো ' . $this->num($ap['count']) . ' টা বিষয় আছে।') : '',
        ]);
    }

    private function a_forecast(): string
    {
        $this->used('forecast');
        $f = $this->I()->forecast(3);
        if (!($f['ok'] ?? false)) {
            return $this->say([$this->open('ok'),
                $this->t(sprintf('I only have %s %s of booked history, which is not enough to project from honestly.',
                            $this->num($f['months'] ?? 0), Phrase::plural((int) ($f['months'] ?? 0), 'month', 'months')),
                         sprintf('আমার কাছে মাত্র %s মাসের হিসাব আছে, এটুকু দিয়ে সৎভাবে পূর্বাভাস দেওয়া যায় না।', $this->num($f['months'] ?? 0))),
                $this->act('give it two more closed months and the projection will mean something.',
                           'আরও দুইটা মাস বন্ধ হোক, তখন পূর্বাভাসের একটা মানে দাঁড়াবে।')]);
        }
        $dir = (string) $f['direction'];
        $mood = $dir === 'up' ? 'good' : ($dir === 'down' ? 'bad' : 'ok');
        $months = Phrase::join(array_map(fn($x) => Phrase::monthName($x, $this->L()), $f['labels']), $this->L());
        $inc = $this->f($f['income_total']);
        $prof = $this->f($f['profit_total']);
        $per = $this->f($f['slope_per_month']);

        $dirWord = $this->t($dir === 'up' ? 'rising' : ($dir === 'down' ? 'falling' : 'flat'),
                            $dir === 'up' ? 'উপরের দিকে' : ($dir === 'down' ? 'নিচের দিকে' : 'সমান'));

        $caveat = ($f['confidence'] === 'thin')
            ? $this->t('Treat it as a direction, not a number — five months of history is a thin base.',
                       'একে সংখ্যা না ধরে দিক ধরুন — পাঁচ মাসের ভিত্তি খুব পাতলা।')
            : '';

        $neg = $inc < 0
            ? $this->t('The line actually runs below zero, which is the trend telling you booked revenue has been shrinking month on month, not that you will earn a negative amount.',
                       'রেখাটা আসলে শূন্যের নিচে নেমে যাচ্ছে — এর মানে ঋণাত্মক আয় হবে না, মানে হলো প্রতি মাসে বুক করা আয় কমতে কমতে নামছে।')
            : '';

        return $this->say([
            $this->open($mood),
            $this->t(sprintf('On %s months of history the trend is %s, about %s a month.',
                        $this->num($f['months']), $dirWord, $this->m(abs($per))),
                     sprintf('%s মাসের হিসাব ধরে প্রবণতা %s, মাসে প্রায় %s করে।',
                        $this->num($f['months']), $dirWord, $this->m(abs($per)))),
            $this->t(sprintf('Carried into %s that points at %s of income and %s of profit.',
                        $months, $this->m($inc), $this->m($prof)),
                     sprintf('%s পর্যন্ত টানলে দাঁড়ায় আয় %s আর মুনাফা %s।',
                        $months, $this->m($inc), $this->m($prof))),
            $neg,
            $caveat,
            $dir === 'down'
                ? $this->act('the pipeline is the lever here, not cost-cutting — look at what is not closing.',
                             'এখানে হাতিয়ার খরচ কমানো না, পাইপলাইন — কোনটা বন্ধ হচ্ছে না সেটা দেখুন।')
                : $this->act('hold the pace and keep collections tight.',
                             'গতিটা ধরে রাখুন আর আদায়ে ঢিল দেবেন না।'),
        ]);
    }

    /* ================= CASH ================= */

    private function a_cash(): string
    {
        $this->used('get_cash_position');
        $r = $this->A()->cash();
        $rw = $this->I()->runway();
        $total = $this->f($r['total'] ?? 0);
        $accts = $r['accounts'] ?? [];
        $live = array_values(array_filter($accts, fn($a) => abs($this->f($a['balance'] ?? 0)) > 0.5));
        $top = $live[0] ?? ($accts[0] ?? null);
        $mood = $rw['months_covered'] === null ? 'ok' : Phrase::moodHigh((float) $rw['months_covered'], 3, 1.5);

        $where = $top
            ? $this->t(sprintf('Most of it — %s — is sitting in %s.', $this->m($this->f($top['balance'] ?? 0)), (string) ($top['name'] ?? '')),
                       sprintf('তার বেশিরভাগ, %s, আছে %s-এ।', $this->m($this->f($top['balance'] ?? 0)), (string) ($top['name'] ?? '')))
            : '';

        $cover = $rw['months_covered'] !== null
            ? $this->t(sprintf('Against a burn of %s a month that is about %s months of cover.',
                        $this->m($this->f($rw['monthly_burn'])), $this->num($rw['months_covered'])),
                       sprintf('মাসে %s খরচের বিপরীতে এটা প্রায় %s মাসের কভার।',
                        $this->m($this->f($rw['monthly_burn'])), $this->num($rw['months_covered'])))
            : '';

        $advice = ($rw['months_covered'] !== null && $rw['months_covered'] < 1.5)
            ? $this->act('that is under two months — chase collections this week rather than next.',
                         'এটা দুই মাসেরও কম — আদায়ের কাজটা পরের সপ্তাহে না, এই সপ্তাহেই ধরুন।')
            : $this->act('comfortable enough; keep an eye on what falls due in the next seven days.',
                         'মোটামুটি স্বস্তির অবস্থা; সামনের সাত দিনে কী কী দিতে হবে সেদিকে চোখ রাখুন।');

        return $this->say([
            $this->open($mood),
            $this->t(sprintf('Cash and bank together come to %s across %s %s.',
                        $this->m($total), $this->num(count($live) ?: count($accts)), Phrase::plural(count($live) ?: count($accts), 'account', 'accounts')),
                     sprintf('হাতে আর ব্যাংকে মিলিয়ে আছে %s, %s টা অ্যাকাউন্টে।',
                        $this->m($total), $this->num(count($live) ?: count($accts)))),
            $where, $cover, $advice,
        ]);
    }

    private function a_bank_accounts(): string
    {
        $this->used('get_cash_position');
        $r = $this->A()->cash();
        $accts = array_values(array_filter($r['accounts'] ?? [], fn($a) => abs($this->f($a['balance'] ?? 0)) > 0.5));
        if (!$accts) {
            return $this->say([$this->open('warn'),
                $this->t('Every bank leaf is reading zero. That normally means the opening balances were never entered, not that the accounts are empty.',
                         'সব ব্যাংক হিসাব শূন্য দেখাচ্ছে। সাধারণত এর মানে ওপেনিং ব্যালেন্স বসানো হয়নি, অ্যাকাউন্ট খালি না।'),
                $this->act('open Accounts → Banks and check whether opening balances were ever posted.',
                           'হিসাব → ব্যাংক-এ গিয়ে দেখুন ওপেনিং ব্যালেন্স আদৌ বসানো হয়েছে কি না।')]);
        }
        $lines = [];
        foreach (array_slice($accts, 0, 6) as $a) {
            $lines[] = (string) ($a['name'] ?? '') . ' ' . $this->m($this->f($a['balance'] ?? 0));
        }
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('%s %s carrying money, %s in total.', $this->num(count($accts)),
                        Phrase::plural(count($accts), 'account is', 'accounts are'), $this->m($this->f($r['total'] ?? 0))),
                     sprintf('%s টা অ্যাকাউন্টে টাকা আছে, মোট %s।', $this->num(count($accts)), $this->m($this->f($r['total'] ?? 0)))),
            Phrase::join($lines, $this->L()) . $this->t('.', '।'),
            count($accts) > 6 ? $this->t('The rest hold smaller amounts.', 'বাকিগুলোতে অল্প অল্প টাকা।') : '',
        ]);
    }

    private function a_petty_cash(): string
    {
        $this->used('get_cash_position');
        $r = $this->A()->cash();
        $petty = [];
        foreach ($r['accounts'] ?? [] as $a) {
            $n = mb_strtolower((string) ($a['name'] ?? ''), 'UTF-8');
            if (strpos($n, 'petty') !== false || strpos($n, 'cash') !== false) $petty[] = $a;
        }
        $tot = 0.0;
        foreach ($petty as $p) $tot += $this->f($p['balance'] ?? 0);
        return $this->say([
            $this->open($tot > 0 ? 'ok' : 'warn'),
            $this->t(sprintf('Petty cash and the cash floats hold %s across %s %s.', $this->m($tot), $this->num(count($petty)), Phrase::plural(count($petty), 'account', 'accounts')),
                     sprintf('পেটি ক্যাশ আর ক্যাশ ফ্লোট মিলিয়ে আছে %s, %s টা হিসাবে।', $this->m($tot), $this->num(count($petty)))),
            $this->t('The pool is 1011, the factory float 1012, office cash 1013 and the float account 1015 — anything over the float lands on 2240 with the custodian named as the party.',
                     'পুল হলো ১০১১, ফ্যাক্টরি ফ্লোট ১০১২, অফিস ক্যাশ ১০১৩ আর ফ্লোট হিসাব ১০১৫ — ফ্লোটের বেশি হলে ২২৪০-এ যায়, কাস্টোডিয়ানকে পার্টি ধরে।'),
            $this->act('Accounts → Petty Cash has the per-custodian statement if a float looks off.',
                       'কোনো ফ্লোট গড়বড় লাগলে হিসাব → পেটি ক্যাশ-এ প্রতিটি কাস্টোডিয়ানের স্টেটমেন্ট আছে।'),
        ]);
    }

    private function a_burn_runway(): string
    {
        $this->used('runway');
        $rw = $this->I()->runway();
        $mc = $rw['months_covered'];
        $mood = $mc === null ? 'ok' : Phrase::moodHigh((float) $mc, 3, 1.5);
        return $this->say([
            $this->open($mood),
            $this->t(sprintf('You are spending about %s a month — expenses plus payroll, averaged over the last %s months.',
                        $this->m($this->f($rw['monthly_burn'])), $this->num($rw['basis_months'])),
                     sprintf('মাসে খরচ হচ্ছে প্রায় %s — খরচ আর বেতন মিলিয়ে, শেষ %s মাসের গড়।',
                        $this->m($this->f($rw['monthly_burn'])), $this->num($rw['basis_months']))),
            $this->t(sprintf('Payroll alone is %s%% of that.', $this->num((int) $rw['payroll_share'])),
                     sprintf('তার %s%% ই কেবল বেতন।', $this->num((int) $rw['payroll_share']))),
            $mc !== null
                ? $this->t(sprintf('Cash of %s covers roughly %s months at that rate.', $this->m($this->f($rw['cash'])), $this->num($mc)),
                           sprintf('হাতের %s দিয়ে ওই হারে চলবে প্রায় %s মাস।', $this->m($this->f($rw['cash'])), $this->num($mc)))
                : '',
            ($mc !== null && $mc < 2)
                ? $this->act('with payroll that dominant, collections are the only fast lever — the cost side barely moves.',
                             'বেতনের ভাগ যখন এত বড়, তখন দ্রুত কাজ করার একমাত্র জায়গা আদায় — খরচের দিকে নাড়াচাড়ার সুযোগ কম।')
                : $this->act('keep it here; nothing needs cutting.', 'এই জায়গাতেই ধরে রাখুন; কাটার দরকার নেই।'),
        ]);
    }

    /* ================= RECEIVABLE / PAYABLE ================= */

    private function a_receivables(): string { return $this->sideAnswer('receive'); }
    private function a_payables(): string { return $this->sideAnswer('pay'); }

    private function sideAnswer(string $side): string
    {
        $this->used($side === 'receive' ? 'get_receivables' : 'get_payables');
        $ag = $this->I()->aging($side);
        $total = $this->f($ag['total']);
        $ov = $this->f($ag['overdue']);
        $isAr = $side === 'receive';

        if ($total <= 0) {
            return $this->say([
                $this->open('ok'),
                $isAr
                    ? $this->t('There is nothing open on the receivable side — no unpaid schedules and no open invoices.',
                               'পাওনার দিকে কিছু খোলা নেই — অপরিশোধিত সূচিও নেই, খোলা ইনভয়েসও নেই।')
                    : $this->t('Nothing is outstanding on the payable side.', 'দেনার দিকে কিছু বাকি নেই।'),
                $isAr
                    ? $this->t('Worth knowing why: receivables in this ERP live in Payment Schedules, and sales are being booked without one. So a zero here means nothing was scheduled, not that every customer has paid.',
                               'কারণটা জেনে রাখা ভালো: এই ERP-তে পাওনা থাকে পেমেন্ট সূচিতে, আর বিক্রি বসছে সূচি ছাড়াই। তাই এখানে শূন্য মানে কেউ সূচি খোলেনি — সব গ্রাহক টাকা দিয়ে দিয়েছে, তা না।')
                    : '',
                $isAr ? $this->act('if customers do owe you, the schedules have to be raised at the point of sale — otherwise no report can see it.',
                                   'গ্রাহকের কাছে টাকা পাওনা থাকলে বিক্রির সময়েই সূচি খুলতে হবে — নইলে কোনো রিপোর্টই সেটা দেখবে না।') : '',
            ]);
        }

        $mood = $ov > 0 ? ($ov > $total * 0.5 ? 'bad' : 'warn') : 'good';
        $party = $ag['by_party'][0] ?? null;
        $oldest = $ag['oldest'] ?? null;

        $head = $isAr
            ? $this->t(sprintf('%s is owed to you, %s of it already past its date.', $this->m($total), $this->m($ov)),
                       sprintf('আপনার পাওনা %s, তার %s ইতিমধ্যে তারিখ পেরিয়েছে।', $this->m($total), $this->m($ov)))
            : $this->t(sprintf('You owe %s, and %s of it is already overdue.', $this->m($total), $this->m($ov)),
                       sprintf('আপনার দেনা %s, তার %s ইতিমধ্যে বকেয়া।', $this->m($total), $this->m($ov)));

        $who = $party
            ? ($isAr
                ? $this->t(sprintf('%s owes the most — %s across %s %s.', (string) $party['party_name'], $this->m($this->f($party['due'])), $this->num($party['count']), Phrase::plural((int) $party['count'], 'item', 'items')),
                           sprintf('সবচেয়ে বেশি পাওনা %s-এর কাছে — %s, %s টা বিষয়ে।', (string) $party['party_name'], $this->m($this->f($party['due'])), $this->num($party['count'])))
                : $this->t(sprintf('The largest is %s at %s.', (string) $party['party_name'], $this->m($this->f($party['due']))),
                           sprintf('সবচেয়ে বড় দেনা %s-কে, %s।', (string) $party['party_name'], $this->m($this->f($party['due'])))))
            : '';

        $old = $oldest
            ? $this->t(sprintf('The oldest is %s at %s days past due (%s).', (string) $oldest['party'], $this->num($oldest['days_overdue']), (string) $oldest['ref']),
                       sprintf('সবচেয়ে পুরনোটা %s — %s দিন পার (%s)।', (string) $oldest['party'], $this->num($oldest['days_overdue']), (string) $oldest['ref']))
            : '';

        $soon = $this->f($ag['due_in_7_days']) > 0
            ? $this->t(sprintf('%s falls due in the next seven days.', $this->m($this->f($ag['due_in_7_days']))),
                       sprintf('সামনের সাত দিনে দিতে হবে %s।', $this->m($this->f($ag['due_in_7_days']))))
            : '';

        // The invoices and the party ledger disagree when an advance was taken but never
        // applied: the invoice still reads "due" while the money is already banked. Say so,
        // or the boss chases a customer who has paid.
        $recon = '';
        if ($isAr && !empty($ag['reconciliation'])) {
            $x = $ag['reconciliation'][0];
            $bal = $this->f($x['ledger_balance'] ?? 0);
            $recon = $this->t(
                sprintf('Careful with that figure: the party ledger only supports %s. %s shows %s on the invoice but the ledger has them %s — %s.',
                    $this->m($this->f($ag['ledger_receivable'] ?? 0)), (string) $x['party'], $this->m($this->f($x['invoiced_open'] ?? 0)),
                    $bal < 0 ? 'in credit ' . $this->m(abs($bal)) : 'at ' . $this->m($bal), (string) $x['reason']),
                sprintf('ওই অঙ্কটা একটু সাবধানে দেখুন: পার্টি খাতা সমর্থন করে মাত্র %s। %s-এর বিলে %s দেখালেও খাতায় তিনি %s — অগ্রিম নেওয়া হয়েছে, বিলে বসানো হয়নি।',
                    $this->m($this->f($ag['ledger_receivable'] ?? 0)), (string) $x['party'], $this->m($this->f($x['invoiced_open'] ?? 0)),
                    $bal < 0 ? $this->m(abs($bal)) . ' জমা আছেন' : $this->m($bal) . ' বাকি')
            );
        }

        $advice = $recon !== ''
            ? $this->act('get the advances applied to the invoices before anyone makes a collection call.',
                         'তাগাদা দেওয়ার আগে অগ্রিমগুলো বিলের সঙ্গে মিলিয়ে নিন।')
            : ($isAr
            ? $this->act('send the reminder to the largest one first — it is worth more than the other calls together.',
                         'সবচেয়ে বড়টাকে আগে তাগাদা দিন — বাকি সব ফোন মিলিয়েও ওর সমান হবে না।')
            : $this->act('clear the oldest first; age is what turns a payable into a phone call.',
                         'সবচেয়ে পুরনোটা আগে মেটান; দেনা পুরনো হলেই ফোন আসা শুরু হয়।'));

        return $this->say([$this->open($mood), $head, $who, $old, $soon, $recon, $advice]);
    }

    private function a_overdue_payments(): string { return $this->sideAnswer('pay'); }

    /* ================= REPORTS ================= */

    private function a_trial_balance(): string
    {
        $this->used('get_trial_balance');
        $r = $this->A()->trialBalance();
        $ok = (bool) ($r['balanced'] ?? false);
        $d = $this->f($r['total_debit'] ?? 0);
        $c = $this->f($r['total_credit'] ?? 0);
        return $this->say([
            $this->open($ok ? 'good' : 'bad'),
            $ok
                ? $this->t(sprintf('The trial balance ties: %s on both sides.', $this->m($d)),
                           sprintf('রেওয়ামিল মিলছে: দুই দিকেই %s।', $this->m($d)))
                : $this->t(sprintf('The trial balance does not tie — debits %s against credits %s, a gap of %s.', $this->m($d), $this->m($c), $this->m(abs($d - $c))),
                           sprintf('রেওয়ামিল মিলছে না — ডেবিট %s, ক্রেডিট %s, ফারাক %s।', $this->m($d), $this->m($c), $this->m(abs($d - $c)))),
            $ok ? '' : $this->t('In this ERP that gap almost always means a shared posting account was given a company id. Reports filter accounts by company but journal items by entry, so one tagged account quietly breaks a company\'s balance.',
                                'এই ERP-তে ওই ফারাক প্রায় সবসময়ই একটাই কারণে হয়: শেয়ার্ড হিসাবে কোম্পানি আইডি বসে গেছে। রিপোর্ট হিসাব ফিল্টার করে কোম্পানি দিয়ে কিন্তু আইটেম ফিল্টার করে এন্ট্রি দিয়ে, তাই একটা ট্যাগ হয়ে যাওয়া হিসাব চুপচাপ কোম্পানির ব্যালেন্স ভেঙে দেয়।'),
            $ok ? $this->act('nothing to do — the books are internally consistent.', 'কিছু করার নেই — হিসাব নিজের ভেতরে ঠিক আছে।')
                : $this->act('find the shared accounts carrying a company id and set them back to NULL.',
                             'যেসব শেয়ার্ড হিসাবে কোম্পানি আইডি বসেছে সেগুলো খুঁজে আবার NULL করে দিন।'),
        ]);
    }

    private function a_balance_sheet(): string
    {
        $this->used('get_balance_sheet');
        $r = $this->A()->balanceSheet();
        $a = $this->f($r['total_assets'] ?? 0);
        $l = $this->f($r['total_liabilities'] ?? 0);
        $e = $this->f($r['total_equity'] ?? 0);
        $mood = $e >= 0 ? 'ok' : 'bad';
        return $this->say([
            $this->open($mood),
            $this->t(sprintf('Assets %s, liabilities %s, equity %s.', $this->m($a), $this->m($l), $this->m($e)),
                     sprintf('সম্পদ %s, দায় %s, মূলধন %s।', $this->m($a), $this->m($l), $this->m($e))),
            ($r['balanced'] ?? false)
                ? $this->t('It balances.', 'দুই দিক মিলে যাচ্ছে।')
                : $this->t('It does not balance, which points at the same shared-account scoping problem as the trial balance.',
                           'মিলছে না — এটা রেওয়ামিলের মতোই শেয়ার্ড হিসাবের স্কোপ সমস্যার দিকে ইশারা করে।'),
            $e < 0 ? $this->t('Negative equity means accumulated losses have eaten past what was put in.',
                              'ঋণাত্মক মূলধন মানে জমা লোকসান বিনিয়োগ করা টাকাটাও ছাড়িয়ে গেছে।') : '',
            $this->act('Accounts → Balance Sheet prints the same thing line by line.',
                       'হিসাব → স্থিতিপত্র থেকে একই জিনিস লাইন ধরে প্রিন্ট হয়।'),
        ]);
    }

    private function a_profit_loss(): string
    {
        $this->used('get_profit_and_loss');
        $mk = $this->monthKey();
        $from = $mk . '-01';
        $to = date('Y-m-t', strtotime($from));
        if ($mk === substr($this->A()->today(), 0, 7)) $to = $this->A()->today();
        $r = $this->A()->profitAndLoss($from, $to);

        $inc = $this->f($r['income'] ?? 0);
        $dc = $this->f($r['direct_cost'] ?? 0);
        $ox = $this->f($r['opex'] ?? 0);
        $net = $this->f($r['net_profit'] ?? 0);
        $margin = $this->f($r['margin_pct'] ?? 0);
        $mood = $net > 0 ? 'good' : ($net < 0 ? 'bad' : 'ok');
        $period = Phrase::monthName($mk, $this->L());

        $verdict = $net >= 0
            ? $this->t(sprintf('%s came out with a profit of %s on %s of income — a %s margin.', $period, $this->m($net), $this->m($inc), $this->pc($margin)),
                       sprintf('%s মাসে %s আয়ের বিপরীতে মুনাফা %s — মার্জিন %s।', $period, $this->m($inc), $this->m($net), $this->pc($margin)))
            : $this->t(sprintf('%s ran at a loss of %s on %s of income.', $period, $this->m(abs($net)), $this->m($inc)),
                       sprintf('%s মাসে %s আয়ের বিপরীতে লোকসান %s।', $period, $this->m($inc), $this->m(abs($net))));

        $split = $this->t(sprintf('Direct cost took %s and overheads %s.', $this->m($dc), $this->m($ox)),
                          sprintf('সরাসরি খরচ গেছে %s আর সাধারণ খরচ %s।', $this->m($dc), $this->m($ox)));

        $why = ($net < 0 && $ox > $inc)
            ? $this->t('Overheads alone are bigger than the whole income for the period — that is where the loss is, not in pricing.',
                       'সাধারণ খরচই এই সময়ের পুরো আয়ের চেয়ে বেশি — লোকসানটা এখানেই, দামে না।')
            : '';

        return $this->say([$this->open($mood), $verdict, $split, $why,
            $net < 0
                ? $this->act('look at the overhead lines before you touch price — ask me which category is biggest.',
                             'দামে হাত দেওয়ার আগে সাধারণ খরচের লাইনগুলো দেখুন — কোন খাত সবচেয়ে বড় সেটা আমাকে জিজ্ঞেস করুন।')
                : $this->act('hold the margin; Monthly Profit shows whether it is trending up.',
                             'মার্জিনটা ধরে রাখুন; Monthly Profit থেকে বোঝা যাবে এটা উপরে উঠছে কি না।')]);
    }

    private function a_revenue(): string
    {
        $this->used('get_profit_and_loss');
        $mk = $this->monthKey();
        $from = $mk . '-01';
        $to = ($mk === substr($this->A()->today(), 0, 7)) ? $this->A()->today() : date('Y-m-t', strtotime($from));
        $r = $this->A()->profitAndLoss($from, $to);
        $inc = $this->f($r['income'] ?? 0);
        $trend = $this->A()->revenueTrend(4);
        $prev = count($trend) >= 2 ? $this->f($trend[count($trend) - 2]['income'] ?? 0) : null;

        $cmp = '';
        if ($prev !== null && $prev > 0) {
            $delta = ($inc - $prev) / $prev * 100;
            $cmp = $delta >= 0
                ? $this->t(sprintf('That is %s up on the month before.', $this->pc(abs($delta))),
                           sprintf('আগের মাসের চেয়ে %s বেশি।', $this->pc(abs($delta))))
                : $this->t(sprintf('That is %s down on the month before.', $this->pc(abs($delta))),
                           sprintf('আগের মাসের চেয়ে %s কম।', $this->pc(abs($delta))));
        }

        // Epal is a travel and services house — the generic sales tables are empty on purpose
        $where = $this->t(
            'Income here does not come from the generic Sales screen — that table is empty. It comes from four invoice modules: ticket sales, visa sales, contract flight bookings and contract file sales.',
            'এখানে আয় সাধারণ সেলস স্ক্রিন থেকে আসে না — ওই টেবিল খালি। আয় আসে চারটা ইনভয়েস মডিউল থেকে: টিকিট সেলস, ভিসা সেলস, কন্ট্রাক্ট ফ্লাইট বুকিং আর কন্ট্রাক্ট ফাইল সেলস।');

        // The books only hold what was journalised, and the desks invoice ahead of posting.
        // Quoting the ledger alone understates the month — say what was actually invoiced.
        $booked = '';
        $sb = $this->A()->salesBooked($from, $to);
        $invoiced = $this->f($sb['invoiced'] ?? 0);
        if ($invoiced > 0 && $invoiced - $inc > 1000) {
            $this->used('get_sales');
            $lines = implode(', ', array_map(fn($l) => $l['line'] . ' ' . $this->m($this->f($l['invoiced'])), array_slice($sb['by_line'] ?? [], 0, 3)));
            $booked = $this->t(
                sprintf('But that is only the books. The desks actually invoiced %s across %s invoices this period (%s) — %s of it has not been journalised yet, so the ledger shows %s of the real business.',
                    $this->m($invoiced), $this->num($sb['invoices'] ?? 0), $lines, $this->m($invoiced - $inc), $this->pc($invoiced > 0 ? $inc / $invoiced * 100 : 0)),
                sprintf('তবে ওটা কেবল খাতার হিসাব। এই সময়ে ডেস্কগুলো আসলে বিল করেছে %s, %s টা ইনভয়েসে (%s) — তার %s এখনো জার্নাল হয়নি, তাই খাতা আসল ব্যবসার %s দেখাচ্ছে।',
                    $this->m($invoiced), $this->num($sb['invoices'] ?? 0), $lines, $this->m($invoiced - $inc), $this->pc($invoiced > 0 ? $inc / $invoiced * 100 : 0)));
        }

        return $this->say([
            $this->open($inc > 0 ? 'ok' : 'warn'),
            $this->t(sprintf('Income booked in %s is %s.', Phrase::monthName($mk, 'en'), $this->m($inc)),
                     sprintf('%s মাসে বুক হওয়া আয় %s।', Phrase::monthName($mk, 'bn'), $this->m($inc))),
            $cmp,
            $inc <= 0 ? $this->t('Nothing has reached a 4xxx income account for this period yet.',
                                 'এই সময়ের জন্য এখনো ৪xxx আয়ের হিসাবে কিছু পৌঁছায়নি।') : '',
            $where,
            $booked,
            $this->act('the cost side sits in ticket purchases and visa processing — ask me for the margin and I will net them off.',
                       'খরচের দিকটা আছে টিকিট পারচেজ আর ভিসা প্রসেসিং-এ — মার্জিন জানতে চাইলে দুটো বাদ দিয়ে বের করে দেব।'),
        ]);
    }

    private function a_expenses(): string
    {
        $this->used('get_expenses_vs_budget');
        $mk = $this->monthKey();
        $cat = $this->I()->expenseByCategory($mk);
        $total = $this->f($cat['total']);
        $period = Phrase::monthName($mk, $this->L());
        $top = $cat['categories'][0] ?? null;

        if ($total <= 0) {
            return $this->say([$this->open('ok'),
                $this->t(sprintf('Nothing has been booked as an expense in %s.', $period),
                         sprintf('%s মাসে খরচ হিসেবে কিছু বসেনি।', $period)),
                $this->act('an expense only posts once it is approved — check whether any are still sitting pending.',
                           'খরচ অনুমোদন হলে তবেই এন্ট্রি হয় — দেখুন কোনোটা pending পড়ে আছে কি না।')]);
        }
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('%s of expenses in %s across %s categories.', $this->m($total), $period, $this->num($cat['category_count'])),
                     sprintf('%s মাসে খরচ %s, %s টা খাতে।', $period, $this->m($total), $this->num($cat['category_count']))),
            $top ? $this->t(sprintf('%s is the biggest at %s, %s of everything.', $top['category'], $this->m($this->f($top['amount'])), $this->pc($this->f($top['pct']))),
                            sprintf('সবচেয়ে বড় খাত %s — %s, মোট খরচের %s।', $top['category'], $this->m($this->f($top['amount'])), $this->pc($this->f($top['pct'])))) : '',
            $this->act('ask me for the breakdown by category if you want the rest of the list.',
                       'বাকি তালিকাটা চাইলে খাত অনুযায়ী ভাগ করে দিতে বলুন।'),
        ]);
    }

    private function a_expense_by_category(): string
    {
        $this->used('get_expenses_vs_budget');
        $mk = $this->monthKey();
        $cat = $this->I()->expenseByCategory($mk, $this->c['slots']['top'] ?: 6);
        $total = $this->f($cat['total']);
        if ($total <= 0) return $this->a_expenses();

        $lines = [];
        foreach ($cat['categories'] as $r) {
            $lines[] = $r['category'] . ' ' . $this->m($this->f($r['amount'])) . ' (' . $this->pc($this->f($r['pct'])) . ')';
        }
        $top = $cat['categories'][0];
        $concentrated = $this->f($top['pct']) > 50;

        return $this->say([
            $this->open($concentrated ? 'warn' : 'ok'),
            $this->t(sprintf('Where %s went in %s: ', $this->m($total), Phrase::monthName($mk, 'en')),
                     sprintf('%s মাসে %s কোথায় গেছে: ', Phrase::monthName($mk, 'bn'), $this->m($total))),
            Phrase::join($lines, $this->L()) . $this->t('.', '।'),
            $concentrated
                ? $this->t(sprintf('%s alone is more than half the month.', $top['category']),
                           sprintf('একা %s খাতই মাসের অর্ধেকের বেশি।', $top['category']))
                : '',
            $this->act('Accounts → Expenses → Budget Setup is where you cap the one that is running away.',
                       'যেটা লাগামছাড়া, তার সীমা বসানোর জায়গা হিসাব → খরচ → বাজেট সেটআপ।'),
        ]);
    }

    private function a_budget(): string
    {
        $this->used('get_expenses_vs_budget');
        $r = $this->A()->expensesVsBudget($this->monthKey());
        $spent = $this->f($r['total_spent'] ?? 0);
        $budget = $this->f($r['total_budget'] ?? 0);
        $over = $r['over'] ?? [];

        if ($budget <= 0) {
            return $this->say([$this->open('warn'),
                $this->t(sprintf('No budget has been set for %s, so I can tell you the spend — %s — but not whether it is too much.',
                            Phrase::monthName($r['month'] ?? $this->monthKey(), 'en'), $this->m($spent)),
                         sprintf('%s মাসের জন্য কোনো বাজেট বসানো নেই, তাই খরচটা বলতে পারি — %s — কিন্তু বেশি হলো কি না বলতে পারি না।',
                            Phrase::monthName($r['month'] ?? $this->monthKey(), 'bn'), $this->m($spent))),
                $this->act('set the ceilings once in Expenses → Budget Setup and every month after that answers itself.',
                           'খরচ → বাজেট সেটআপ-এ একবার সীমাগুলো বসিয়ে দিন, তারপর প্রতি মাসের উত্তর নিজেই আসবে।')]);
        }
        $pct = $budget > 0 ? $spent / $budget * 100 : 0;
        $mood = $pct > 100 ? 'bad' : ($pct > 85 ? 'warn' : 'good');
        $overText = $over
            ? $this->t('Over the line: ' . Phrase::join(array_map(fn($x) => $x['category'] . ' at ' . $this->pc($this->f($x['pct'])), $over), 'en') . '.',
                       'সীমা ছাড়িয়েছে: ' . Phrase::join(array_map(fn($x) => $x['category'] . ' ' . $this->pc($this->f($x['pct'])), $over), 'bn') . '।')
            : $this->t('Nothing is over its ceiling.', 'কোনো খাতই সীমা ছাড়ায়নি।');

        return $this->say([
            $this->open($mood),
            $this->t(sprintf('%s spent against a %s budget — %s of it used.', $this->m($spent), $this->m($budget), $this->pc($pct)),
                     sprintf('%s বাজেটের বিপরীতে খরচ %s — ব্যবহার হয়েছে %s।', $this->m($budget), $this->m($spent), $this->pc($pct))),
            $overText,
        ]);
    }

    private function a_account_ledger(): string
    {
        $code = $this->c['slots']['account_code'];
        if ($code === null) {
            return $this->say([$this->open('ok'),
                $this->t('Tell me the account code and I will read its ledger — 1011 is the petty-cash pool, 1311 customer receivable, 2111 supplier payable, 6110 salary expense, 2210 salaries payable.',
                         'হিসাব কোডটা বলুন, খতিয়ান পড়ে দিচ্ছি — ১০১১ পেটি ক্যাশ পুল, ১৩১১ গ্রাহক পাওনা, ২১১১ সরবরাহকারী দেনা, ৬১১০ বেতন খরচ, ২২১০ বেতন প্রদেয়।')]);
        }
        $this->used('get_account_ledger');
        $r = $this->c['tools']->run('get_account_ledger', ['code' => $code]);
        if (!is_array($r) || isset($r['error'])) {
            return $this->say([$this->open('warn'),
                $this->t(sprintf('I could not find account %s in the chart.', $code),
                         sprintf('হিসাব তালিকায় %s কোডটা পেলাম না।', Phrase::bnDigits($code)))]);
        }
        $bal = $this->f($r['closing_balance'] ?? 0);
        $n = (int) ($r['postings'] ?? 0);
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('Account %s%s closes at %s after %s %s.', $code,
                        isset($r['name']) ? ' — ' . $r['name'] . ' —' : '', $this->m($bal), $this->num($n), Phrase::plural($n, 'posting', 'postings')),
                     sprintf('হিসাব %s%s — %s টা এন্ট্রির পর ক্লোজিং ব্যালেন্স %s।', Phrase::bnDigits($code),
                        isset($r['name']) ? ' (' . $r['name'] . ')' : '', $this->num($n), $this->m($bal))),
            $n === 0 ? $this->t('Nothing has ever been posted to it.', 'এই হিসাবে কখনো কিছু বসেনি।') : '',
            $this->act('Accounts → General Ledger prints the full run of entries.',
                       'হিসাব → সাধারণ খতিয়ান থেকে পুরো এন্ট্রির তালিকা প্রিন্ট হয়।'),
        ]);
    }

    private function a_journal(): string
    {
        $A = $this->A();
        $this->used('get_journal');
        $entries = $A->rows('journal_entries');
        $bySource = [];
        foreach ($entries as $e) $bySource[(string) ($e['source'] ?? 'other')] = ($bySource[(string) ($e['source'] ?? 'other')] ?? 0) + 1;
        arsort($bySource);
        $bits = [];
        foreach (array_slice($bySource, 0, 4, true) as $k => $v) $bits[] = $this->num($v) . ' ' . str_replace('_', ' ', $k);

        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('I can see %s journal entries in the window I hold.', $this->num(count($entries))),
                     sprintf('আমার হাতে থাকা সময়সীমায় %s টা জার্নাল এন্ট্রি আছে।', $this->num(count($entries)))),
            $bits ? $this->t('By source: ' . Phrase::join($bits, 'en') . '.', 'উৎস অনুযায়ী: ' . Phrase::join($bits, 'bn') . '।') : '',
            $this->t('Every one is double entry, and a wrong one is never edited — a reversal is posted against it instead.',
                     'প্রতিটাই দুতরফা, আর ভুল হলে কখনো এডিট হয় না — বদলে একটা উল্টো এন্ট্রি বসে।'),
            $this->act('Accounts → Journal Entries has the voucher for each.',
                       'প্রতিটার ভাউচার আছে হিসাব → জার্নাল এন্ট্রি-তে।'),
        ]);
    }

    private function a_loans(): string
    {
        $this->used('get_loans');
        $r = $this->I()->loans();
        if (($r['count'] ?? 0) === 0) {
            return $this->say([$this->open('good'),
                $this->t('No staff loan is running — nothing outstanding.', 'চলমান কোনো কর্মী-ঋণ নেই — কিছু বাকি নেই।')]);
        }
        $top = $r['rows'][0];
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('%s %s running, %s still outstanding.', $this->num($r['count']), Phrase::plural((int) $r['count'], 'loan is', 'loans are'), $this->m($this->f($r['outstanding']))),
                     sprintf('%s টা ঋণ চলছে, বাকি আছে %s।', $this->num($r['count']), $this->m($this->f($r['outstanding'])))),
            $this->t(sprintf('%s owes %s of an original %s, coming back at %s a month%s.',
                        $top['name'], $this->m($this->f($top['balance'])), $this->m($this->f($top['amount'])), $this->m($this->f($top['emi'])),
                        $top['months_left'] ? ' — about ' . $this->num($top['months_left']) . ' months left' : ''),
                     sprintf('%s এর কাছে বাকি %s (মূল ছিল %s), মাসে %s করে ফিরছে%s।',
                        $top['name'], $this->m($this->f($top['balance'])), $this->m($this->f($top['amount'])), $this->m($this->f($top['emi'])),
                        $top['months_left'] ? ' — আরও প্রায় ' . $this->num($top['months_left']) . ' মাস' : '')),
            $this->t('The instalment comes off the payslip on its own; you do not have to chase it.',
                     'কিস্তিটা পে-স্লিপ থেকে নিজে থেকেই কাটে; আপনাকে তাগাদা দিতে হয় না।'),
        ]);
    }

    private function a_advances(): string
    {
        $this->used('get_advances');
        $r = $this->I()->advances();
        if (($r['count'] ?? 0) === 0) {
            return $this->say([$this->open('good'), $this->t('No salary advance is on the books.', 'খাতায় কোনো অগ্রিম বেতন নেই।')]);
        }
        $mood = $this->f($r['outstanding']) > 100000 ? 'warn' : 'ok';
        return $this->say([
            $this->open($mood),
            $this->t(sprintf('%s advances have been given, with %s handed out and not yet recovered.',
                        $this->num($r['count']), $this->m($this->f($r['outstanding']))),
                     sprintf('মোট %s টা অগ্রিম দেওয়া হয়েছে, এর মধ্যে %s দেওয়া হয়ে গেছে কিন্তু এখনো ফেরত আসেনি।',
                        $this->num($r['count']), $this->m($this->f($r['outstanding'])))),
            ($r['pending'] ?? 0)
                ? $this->t(sprintf('%s more %s waiting for your approval.', $this->num($r['pending']), Phrase::plural((int) $r['pending'], 'is', 'are')),
                           sprintf('আরও %s টা আপনার অনুমোদনের অপেক্ষায়।', $this->num($r['pending'])))
                : '',
            $this->t('Recovery happens through payslip instalments, so it comes back slowly rather than in one go.',
                     'ফেরত আসে পে-স্লিপের কিস্তিতে, তাই একবারে না — ধীরে ধীরে।'),
        ]);
    }

    private function a_tax(): string
    {
        $this->used('get_account_ledger');
        return $this->say([
            $this->open('warn'),
            $this->t('The ERP holds the tax accounts — 2270 Income Tax Payable and 2280 TDS/VDS Payable — but it does not produce the Bangladesh VAT or TDS returns. Mushak 6.3 and 9.1 are not built.',
                     'ERP-তে করের হিসাবগুলো আছে — ২২৭০ Income Tax Payable আর ২২৮০ TDS/VDS Payable — কিন্তু বাংলাদেশের ভ্যাট বা টিডিএস রিটার্ন এটা বানায় না। মূসক ৬.৩ আর ৯.১ নেই।'),
            $this->t('Payroll does not compute income tax either — no tax, no provident fund, no gratuity comes off a payslip.',
                     'পে-রোলও আয়কর হিসাব করে না — পে-স্লিপ থেকে কর, প্রভিডেন্ট ফান্ড বা গ্র্যাচুইটি কিছুই কাটে না।'),
            $this->act('ask me for the balance on 2280 and I will read the ledger; the return itself still has to be filed outside the system.',
                       '২২৮০-এর ব্যালেন্স জানতে চাইলে খতিয়ান পড়ে দেব; রিটার্নটা এখনো সিস্টেমের বাইরে দিতে হবে।'),
        ]);
    }

    private function a_company_compare(): string
    {
        $this->used('by_company');
        $r = $this->I()->byCompany();
        $cos = $r['companies'] ?? [];
        $live = array_values(array_filter($cos, fn($c) => $c['income'] != 0 || $c['expense'] != 0 || $c['headcount'] > 0));
        if (!$live) {
            return $this->say([$this->open('ok'), $this->t('No company has any activity booked this month yet.', 'এ মাসে কোনো কোম্পানিতেই এখনো কিছু বসেনি।')]);
        }
        $best = $live[0];
        $worst = end($live);
        $lines = [];
        foreach (array_slice($live, 0, 5) as $c) {
            $lines[] = $c['name'] . ' ' . ($c['profit'] >= 0 ? '+' : '−') . $this->m(abs($this->f($c['profit'])));
        }
        return $this->say([
            $this->open($best['profit'] > 0 ? 'ok' : 'warn'),
            $this->t(sprintf('For %s, by profit: ', Phrase::monthName((string) $r['month'], 'en')),
                     sprintf('%s মাসে, মুনাফা অনুযায়ী: ', Phrase::monthName((string) $r['month'], 'bn'))),
            Phrase::join($lines, $this->L()) . $this->t('.', '।'),
            $this->t(sprintf('%s is carrying the group; %s is the drag.', $best['name'], $worst['name']),
                     sprintf('গ্রুপটা টানছে %s; আর ভার হয়ে আছে %s।', $best['name'], $worst['name'])),
            $this->t(sprintf('Headcount sits mostly in %s with %s people.', $best['name'], $this->num($best['headcount'])),
                     sprintf('জনবলের বেশিরভাগ %s-এ, %s জন।', $best['name'], $this->num($best['headcount']))),
            $this->act('the loss-making side is worth an hour before the profitable one is worth a review.',
                       'যেটা লোকসান দিচ্ছে সেটায় এক ঘণ্টা দেওয়া, যেটা লাভ দিচ্ছে সেটা রিভিউ করার চেয়ে বেশি কাজে দেবে।'),
        ]);
    }

    /* ================= PAYROLL & PEOPLE ================= */

    private function a_payroll(): string
    {
        // "payslip of <name>" is a different question from "the payroll"
        if ($this->c['slots']['name_hint']) {
            $person = $this->personPayslip();
            if ($person !== null) return $person;
        }
        $this->used('get_payroll');
        $mk = $this->monthKey();
        $r = $this->A()->payroll($mk);
        $heads = (int) ($r['heads'] ?? 0);
        if ($heads === 0) {
            $r = $this->A()->payroll(null);
            $mk = (string) ($r['month'] ?? $mk);
            $heads = (int) ($r['heads'] ?? 0);
        }
        if ($heads === 0) {
            return $this->say([$this->open('warn'),
                $this->t('No payslips have been generated for that month yet.', 'ওই মাসের জন্য এখনো কোনো পে-স্লিপ তৈরি হয়নি।'),
                $this->act('payroll runs on the 1st at 01:00 for the month before — if it is past that, something stopped the job.',
                           'পে-রোল চলে ১ তারিখ রাত ১টায়, আগের মাসের জন্য — সময় পেরিয়ে গিয়ে থাকলে কাজটা কোথাও আটকেছে।')]);
        }
        $pending = (int) ($r['pending_count'] ?? 0);
        $mood = $pending > 0 ? 'warn' : 'good';
        $ded = $this->f($r['deductions'] ?? 0);
        $gross = $this->f($r['gross'] ?? 0);

        return $this->say([
            $this->open($mood),
            $this->t(sprintf('%s payroll: %s payslips, %s gross, %s deducted, %s net.',
                        Phrase::monthName($mk, 'en'), $this->num($heads), $this->m($gross), $this->m($ded), $this->m($this->f($r['net'] ?? 0))),
                     sprintf('%s মাসের পে-রোল: %s টা স্লিপ, গ্রস %s, কর্তন %s, নিট %s।',
                        Phrase::monthName($mk, 'bn'), $this->num($heads), $this->m($gross), $this->m($ded), $this->m($this->f($r['net'] ?? 0)))),
            $pending > 0
                ? $this->t(sprintf('%s of them are still unpaid — %s.', $this->num($pending), $this->m($this->f($r['pending_net'] ?? 0))),
                           sprintf('তার মধ্যে %s জনের বেতন এখনো দেওয়া হয়নি — %s।', $this->num($pending), $this->m($this->f($r['pending_net'] ?? 0))))
                : $this->t('Everyone has been paid.', 'সবার বেতন দেওয়া হয়ে গেছে।'),
            $gross > 0 && $ded > 0
                ? $this->t(sprintf('Deductions came to %s of gross.', $this->pc($ded / $gross * 100)),
                           sprintf('কর্তন গ্রসের %s।', $this->pc($ded / $gross * 100)))
                : '',
            $pending > 0
                ? $this->act('unpaid slips sit as 2210 Salaries Payable and open a payment schedule — they are already in your payables.',
                             'অপরিশোধিত স্লিপ ২২১০ Salaries Payable-এ বসে থাকে আর একটা পেমেন্ট সূচি খোলে — মানে ওগুলো আপনার দেনার মধ্যেই আছে।')
                : $this->act('nothing pending on payroll.', 'পে-রোলে কিছু ঝুলে নেই।'),
        ]);
    }

    private function a_payroll_unpaid(): string { return $this->a_payroll(); }

    /** "payslip of Rakib", "Rakib er beton" */
    private function personPayslip(): ?string
    {
        $name = (string) $this->c['slots']['name_hint'];
        $e = $this->A()->findEmployee($name);
        if (!$e) return null;
        $this->used('find_employee');
        $ps = $this->I()->payslips((int) $e['id'], 4);
        if (($ps['count'] ?? 0) === 0) {
            return $this->say([$this->open('warn'),
                $this->t(sprintf('%s is on the books at %s a month, but no payslip has been generated for them yet.',
                            (string) $e['name'], $this->m($this->f($e['salary'] ?? 0))),
                         sprintf('%s এর বেতন খাতায় মাসে %s, কিন্তু তার কোনো পে-স্লিপ এখনো তৈরি হয়নি।',
                            (string) $e['name'], $this->m($this->f($e['salary'] ?? 0))))]);
        }
        $last = $ps['rows'][0];
        $bits = [];
        foreach (['absent' => ['absence', 'অনুপস্থিতি'], 'late' => ['late', 'দেরি'], 'early' => ['early-out', 'আগে বের হওয়া'],
                  'leave' => ['unpaid leave', 'বেতনহীন ছুটি'], 'loan' => ['loan EMI', 'ঋণের কিস্তি'], 'advance' => ['advance', 'অগ্রিম']] as $k => $lbl) {
            if ($this->f($last[$k]) > 0) $bits[] = $this->t($lbl[0], $lbl[1]) . ' ' . $this->m($this->f($last[$k]));
        }

        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('%s — %s: gross %s, net %s, %s.',
                        (string) $e['name'], Phrase::monthName((string) $last['month'], 'en'),
                        $this->m($this->f($last['gross'])), $this->m($this->f($last['net'])),
                        strtolower((string) $last['status']) === 'paid' ? 'paid on ' . Phrase::day((string) $last['paid_on'], 'en') : 'still unpaid'),
                     sprintf('%s — %s: গ্রস %s, নিট %s, %s।',
                        (string) $e['name'], Phrase::monthName((string) $last['month'], 'bn'),
                        $this->m($this->f($last['gross'])), $this->m($this->f($last['net'])),
                        strtolower((string) $last['status']) === 'paid' ? Phrase::day((string) $last['paid_on'], 'bn') . ' তারিখে পরিশোধ' : 'এখনো বাকি')),
            $bits
                ? $this->t('Deductions: ' . Phrase::join($bits, 'en') . '.', 'কর্তন: ' . Phrase::join($bits, 'bn') . '।')
                : $this->t('Nothing was deducted that month.', 'ওই মাসে কিছুই কাটা হয়নি।'),
            $this->t(sprintf('I hold %s %s of slips for them.', $this->num($ps['count']), Phrase::plural((int) $ps['count'], 'month', 'months')),
                     sprintf('তার %s মাসের স্লিপ আমার কাছে আছে।', $this->num($ps['count']))),
            $this->act('HR → Payslips → Statement prints the full history.',
                       'পুরো ইতিহাস প্রিন্ট হয় এইচআর → পে-স্লিপ → স্টেটমেন্ট থেকে।'),
        ]);
    }

    private function a_deduction_rules(): string
    {
        $rule = Kb::findRule($this->c['norm'], 'deduction_rules');
        $body = $rule ? (string) $rule[$this->L()] : '';
        return $this->say([
            $this->open('ok'),
            $body,
            $this->act('if a number on a slip looks wrong, give me the name and month and I will read the actual figures.',
                       'কোনো স্লিপের অঙ্ক ভুল লাগলে নাম আর মাসটা বলুন, আমি আসল হিসাবটা পড়ে দেব।'),
        ]);
    }

    private function a_overtime(): string
    {
        $this->used('get_attendance_patterns');
        $A = $this->A();
        $mk = $this->monthKey();
        $ot = 0;
        $people = [];
        foreach ($A->rows('attendances') as $a) {
            if (substr((string) ($a['date'] ?? ''), 0, 7) !== $mk) continue;
            $m = (int) ($a['overtime_minutes'] ?? 0);
            if ($m <= 0) continue;
            $ot += $m;
            $people[(int) ($a['user_id'] ?? 0)] = ($people[(int) ($a['user_id'] ?? 0)] ?? 0) + $m;
        }
        arsort($people);
        $topId = array_key_first($people);
        $topName = $topId ? $A->employeeName($topId) : '';

        if ($ot === 0) {
            return $this->say([$this->open('ok'),
                $this->t(sprintf('No overtime minutes are recorded for %s.', Phrase::monthName($mk, 'en')),
                         sprintf('%s মাসে কোনো ওভারটাইম নেই।', Phrase::monthName($mk, 'bn'))),
                $this->t('Remember overtime only starts counting an hour after the shift ends, and it is only paid where the person is marked overtime-eligible.',
                         'মনে রাখবেন, শিফট শেষের এক ঘণ্টা পর থেকে ওভারটাইম গোনা শুরু, আর টাকা পাবে কেবল যার overtime-eligible চিহ্ন আছে।')]);
        }
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('%s recorded %s hours of overtime across the team.', Phrase::monthName($mk, 'en'), $this->num(round($ot / 60, 1))),
                     sprintf('%s মাসে পুরো টিম মিলিয়ে ওভারটাইম %s ঘণ্টা।', Phrase::monthName($mk, 'bn'), $this->num(round($ot / 60, 1)))),
            $topName ? $this->t(sprintf('%s put in the most — %s hours.', $topName, $this->num(round($people[$topId] / 60, 1))),
                                sprintf('সবচেয়ে বেশি দিয়েছে %s — %s ঘণ্টা।', $topName, $this->num(round($people[$topId] / 60, 1)))) : '',
            $this->t('Only the overtime-eligible ones actually get paid for it.',
                     'এর মধ্যে টাকা পাবে কেবল যাদের overtime-eligible চিহ্ন আছে।'),
        ]);
    }

    private function a_headcount(): string
    {
        $this->used('get_headcount');
        $h = $this->I()->headcount();
        $depts = $h['by_department'] ?? [];
        $top = $depts[0] ?? null;
        $joiners = $h['recent_joiners'] ?? [];

        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('%s people are active, carrying %s of salary a month.',
                        $this->num($h['active']), $this->m($this->f($h['monthly_salary']))),
                     sprintf('সক্রিয় কর্মী %s জন, মাসে বেতন বাবদ %s।',
                        $this->num($h['active']), $this->m($this->f($h['monthly_salary'])))),
            $top ? (strtolower((string) $top['department']) === 'unassigned'
                ? $this->t(sprintf('%s of them have no department set — that is a gap in the records, not a department.', $this->num($top['headcount'])),
                           sprintf('তাদের %s জনের কোনো বিভাগ বসানো নেই — এটা রেকর্ডের ফাঁক, কোনো বিভাগ না।', $this->num($top['headcount'])))
                : $this->t(sprintf('The largest department is %s with %s.', $top['department'], $this->num($top['headcount'])),
                           sprintf('সবচেয়ে বড় বিভাগ %s — %s জন।', $top['department'], $this->num($top['headcount'])))) : '',
            $joiners
                ? $this->t(sprintf('%s joined in the last ninety days: %s.', $this->num(count($joiners)), Phrase::join(array_column($joiners, 'name'), 'en')),
                           sprintf('শেষ নব্বই দিনে যোগ দিয়েছে %s জন: %s।', $this->num(count($joiners)), Phrase::join(array_column($joiners, 'name'), 'bn')))
                : $this->t('Nobody has joined in the last ninety days.', 'শেষ নব্বই দিনে কেউ যোগ দেয়নি।'),
            $this->act('HR → Users has the list; ask me for a person by name and I will pull their record.',
                       'তালিকা আছে এইচআর → ইউজার-এ; কারও নাম বললে আমি তার রেকর্ড বের করে দেব।'),
        ]);
    }

    private function a_departments(): string
    {
        $this->used('get_headcount');
        $r = $this->I()->byDepartment();
        $lines = [];
        foreach (array_slice($r['departments'], 0, 6) as $d) {
            $lines[] = $d['department'] . ' ' . $this->num($d['headcount']) . $this->t('', ' জন');
        }
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('%s people across %s departments, %s of salary a month.',
                        $this->num($r['total_headcount']), $this->num(count($r['departments'])), $this->m($this->f($r['total_salary']))),
                     sprintf('%s টা বিভাগে মোট %s জন, মাসে বেতন %s।',
                        $this->num(count($r['departments'])), $this->num($r['total_headcount']), $this->m($this->f($r['total_salary'])))),
            Phrase::join($lines, $this->L()) . $this->t('.', '।'),
            $this->act('HR → Departments is where the structure is maintained.',
                       'কাঠামোটা রাখা হয় এইচআর → বিভাগ-এ।'),
        ]);
    }

    private function a_attendance_today(): string
    {
        $this->used('get_attendance_today');
        $r = $this->A()->attendanceToday();
        if ($r['weekend'] ?? false) {
            return $this->say([$this->open('ok'),
                $this->t('It is the weekend — Friday and Saturday here — so nobody is expected in.',
                         'আজ সাপ্তাহিক ছুটি — এখানে শুক্র আর শনি — তাই কারও আসার কথা না।')]);
        }
        $present = (int) ($r['present'] ?? 0);
        $total = (int) ($r['total'] ?? 0);
        $absent = (int) ($r['absent'] ?? 0);
        $late = (int) ($r['late'] ?? 0);
        $pct = $this->f($r['present_pct'] ?? 0);
        $mood = Phrase::moodHigh($pct, 85, 65);

        // before the first punch of the day there is no attendance, which is not the same as an empty office
        if ($present === 0 && $late === 0 && $absent === 0) {
            $last = $this->lastAttendanceDate();
            return $this->say([
                $this->t('No attendance has come through for today yet — the first punches have not landed.',
                         'আজকের হাজিরা এখনো ওঠেনি — প্রথম পাঞ্চগুলো আসেনি।'),
                $last !== '' ? $this->t('The most recent day on record is ' . Phrase::day($last, 'en') . '.',
                                        'সর্বশেষ যেদিনের হাজিরা আছে সেটা ' . Phrase::day($last, 'bn') . '।') : '',
                $this->act('ask me again after the shift starts, or ask for last month\'s attendance instead.',
                           'শিফট শুরুর পর আবার জিজ্ঞেস করুন, অথবা গত মাসের হাজিরা চেয়ে নিন।'),
            ]);
        }

        $absentNames = array_map(fn($a) => (string) ($a['name'] ?? ''), array_slice($r['absent_list'] ?? [], 0, 5));

        return $this->say([
            $this->open($mood),
            $this->t(sprintf('%s of %s are in today — %s.', $this->num($present), $this->num($total), $this->pc($pct)),
                     sprintf('আজ %s জনের মধ্যে %s জন এসেছে — %s।', $this->num($total), $this->num($present), $this->pc($pct))),
            ($absent || $late || ($r['on_leave'] ?? 0))
                ? $this->t(sprintf('%s absent, %s late, %s on leave.', $this->num($absent), $this->num($late), $this->num($r['on_leave'] ?? 0)),
                           sprintf('%s জন অনুপস্থিত, %s জন দেরি করেছে, %s জন ছুটিতে।', $this->num($absent), $this->num($late), $this->num($r['on_leave'] ?? 0)))
                : '',
            $absentNames
                ? $this->t('Absent: ' . Phrase::join($absentNames, 'en') . '.', 'অনুপস্থিত: ' . Phrase::join($absentNames, 'bn') . '।')
                : '',
            $absent > 0
                ? $this->act('each absent day costs that person one day of salary unless an approved leave covers it.',
                             'অনুমোদিত ছুটি না থাকলে প্রতিটা অনুপস্থিত দিনে তার একদিনের বেতন কাটবে।')
                : $this->act('full house — nothing to chase.', 'পুরো টিমই হাজির — তাগাদা দেওয়ার কিছু নেই।'),
        ]);
    }

    /** the newest date that has any attendance row at all */
    private function lastAttendanceDate(): string
    {
        $best = '';
        foreach ($this->A()->rows('attendances') as $a) {
            $d = substr((string) ($a['date'] ?? ''), 0, 10);
            if ($d > $best) $best = $d;
        }
        return $best;
    }

    private function a_late_today(): string
    {
        $this->used('get_attendance_today');
        $A = $this->A();
        $today = $A->today();
        $late = [];
        foreach ($A->rows('attendances') as $a) {
            if (substr((string) ($a['date'] ?? ''), 0, 10) !== $today) continue;
            $m = (int) ($a['late_minutes'] ?? 0);
            if ($m > 0) $late[] = ['name' => $A->employeeName($a['user_id'] ?? null), 'minutes' => $m, 'in' => (string) ($a['check_in'] ?? '')];
        }
        usort($late, fn($x, $y) => $y['minutes'] <=> $x['minutes']);

        if (!$late) {
            return $this->say([$this->open('good'),
                $this->t('Nobody is marked late today.', 'আজ কারও দেরির রেকর্ড নেই।'),
                $this->t('Late is counted against the shift start, and the first two hours in a month are forgiven anyway.',
                         'দেরি গোনা হয় শিফট শুরুর সময় থেকে, আর মাসের প্রথম দুই ঘণ্টা এমনিতেই মাফ।')]);
        }
        $lines = [];
        foreach (array_slice($late, 0, 5) as $l) {
            $lines[] = $l['name'] . ' ' . $this->num($l['minutes']) . $this->t(' min', ' মিনিট');
        }
        return $this->say([
            $this->open(count($late) > 3 ? 'warn' : 'ok'),
            $this->t(sprintf('%s came in late today.', $this->num(count($late))),
                     sprintf('আজ %s জন দেরি করে এসেছে।', $this->num(count($late)))),
            Phrase::join($lines, $this->L()) . $this->t('.', '।'),
            $this->t('None of it costs anything until that person crosses two hours of lateness for the month — then the whole amount is charged.',
                     'মাসে দুই ঘণ্টা না পেরোনো পর্যন্ত এতে কোনো টাকা কাটে না — পেরোলে পুরো সময়ের টাকাই কাটে।'),
        ]);
    }

    private function a_chronic_late(): string
    {
        $this->used('get_attendance_patterns');
        $r = $this->A()->latePatterns(30);
        $ch = $r['chronic_late'] ?? [];
        if (!$ch) {
            return $this->say([$this->open('good'),
                $this->t('Nobody is late on more than a third of their days over the last month.',
                         'গত মাসে কেউই তার এক-তৃতীয়াংশের বেশি দিনে দেরি করেনি।')]);
        }
        $lines = [];
        foreach (array_slice($ch, 0, 4) as $p) {
            $lines[] = $this->t(sprintf('%s on %s days (%s minutes)', $p['name'], $this->num($p['late_days']), $this->num($p['late_minutes'])),
                                sprintf('%s — %s দিন (%s মিনিট)', $p['name'], $this->num($p['late_days']), $this->num($p['late_minutes'])));
        }
        return $this->say([
            $this->open('warn'),
            $this->t(sprintf('%s people are late on 30%% of days or more.', $this->num(count($ch))),
                     sprintf('%s জন তাদের ৩০%% বা বেশি দিনে দেরি করে।', $this->num(count($ch)))),
            Phrase::join($lines, $this->L()) . $this->t('.', '।'),
            $this->act('a warning letter costs nothing and works better than the deduction, which most of them never cross anyway.',
                       'একটা সতর্কীকরণ চিঠি খরচ ছাড়াই কাজ দেয় — কর্তনের চেয়ে ভালো, কারণ বেশিরভাগই ওই সীমা পর্যন্ত পৌঁছায়ই না।'),
        ]);
    }

    private function a_leaves(): string
    {
        $this->used('get_leaves');
        $A = $this->A();
        $pending = $A->pendingLeaves();
        $rows = is_array($pending) ? ($pending['rows'] ?? $pending) : [];
        $n = is_array($rows) ? count($rows) : 0;
        $td = $A->attendanceToday();
        $onLeave = (int) ($td['on_leave'] ?? 0);

        return $this->say([
            $this->open($n > 0 ? 'warn' : 'ok'),
            $onLeave > 0
                ? $this->t(sprintf('%s %s on leave today.', $this->num($onLeave), Phrase::plural($onLeave, 'person is', 'people are')),
                           sprintf('আজ %s জন ছুটিতে আছে।', $this->num($onLeave)))
                : $this->t('Nobody is on leave today.', 'আজ কেউ ছুটিতে নেই।'),
            $n > 0
                ? $this->t(sprintf('%s leave %s waiting for a decision.', $this->num($n), Phrase::plural($n, 'application is', 'applications are')),
                           sprintf('%s টা ছুটির আবেদন সিদ্ধান্তের অপেক্ষায়।', $this->num($n)))
                : $this->t('No application is pending.', 'কোনো আবেদন ঝুলে নেই।'),
            $this->t('A balance is the year\'s entitlement minus what has already been approved — a pending application does not touch it until you decide.',
                     'ছুটির হিসাব হলো বছরের বরাদ্দ বাদ ইতিমধ্যে অনুমোদিত দিন — আপনি সিদ্ধান্ত না দেওয়া পর্যন্ত ঝুলে থাকা আবেদন বরাদ্দে হাত দেয় না।'),
            $n > 0 ? $this->act('unapproved leave gets deducted as absence, so a slow decision costs the person money.',
                                'অনুমোদন না হলে ওটা অনুপস্থিতি ধরে কাটা যায় — তাই দেরিতে সিদ্ধান্ত দিলে ওই কর্মীর টাকা যায়।') : '',
        ]);
    }

    private function a_holidays(): string
    {
        $this->used('get_holidays');
        $h = $this->I()->upcomingHolidays(3);
        if (!$h['next']) {
            return $this->say([$this->open('ok'),
                $this->t(sprintf('The calendar holds %s holidays but none of them are still ahead of us this year.', $this->num($h['total_in_calendar'])),
                         sprintf('ক্যালেন্ডারে %s টা ছুটি আছে, তবে এ বছরের আর কোনোটাই সামনে নেই।', $this->num($h['total_in_calendar'])))]);
        }
        $next = $h['next'];
        $rest = array_slice($h['upcoming'], 1);
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('Next up is %s on %s — %s away.', $next['name'], Phrase::day((string) $next['date'], 'en'), $this->num($next['days_away']) . ' days'),
                     sprintf('পরের ছুটি %s, %s তারিখে — আর %s দিন।', $next['name'], Phrase::day((string) $next['date'], 'bn'), $this->num($next['days_away']))),
            $rest
                ? $this->t('After that: ' . Phrase::join(array_map(fn($x) => $x['name'] . ' (' . Phrase::day((string) $x['date'], 'en') . ')', $rest), 'en') . '.',
                           'তারপর: ' . Phrase::join(array_map(fn($x) => $x['name'] . ' (' . Phrase::day((string) $x['date'], 'bn') . ')', $rest), 'bn') . '।')
                : '',
            $this->t('A day on this calendar is marked holiday in attendance rather than absent, so nobody loses salary for it.',
                     'এই ক্যালেন্ডারের দিনগুলো হাজিরায় অনুপস্থিত না, ছুটি হিসেবে বসে — তাই কারও বেতন কাটে না।'),
        ]);
    }

    private function a_employee_requests(): string
    {
        $this->used('get_requests');
        $r = $this->I()->requests();
        if (($r['count'] ?? 0) === 0) {
            return $this->say([$this->open('good'), $this->t('No employee request is on the books.', 'কর্মীদের কোনো আবেদন নেই।')]);
        }
        $lines = [];
        foreach (array_slice($r['pending_rows'], 0, 4) as $x) {
            $lines[] = $x['name'] . ' — ' . $x['type'] . ($this->f($x['amount']) > 0 ? ' ' . $this->m($this->f($x['amount'])) : '');
        }
        return $this->say([
            $this->open(($r['pending'] ?? 0) > 0 ? 'warn' : 'ok'),
            $this->t(sprintf('%s requests in total, %s still waiting on a decision%s.',
                        $this->num($r['count']), $this->num($r['pending']),
                        $this->f($r['pending_amount']) > 0 ? ' worth ' . $this->m($this->f($r['pending_amount'])) : ''),
                     sprintf('মোট %s টা আবেদন, %s টা এখনো সিদ্ধান্তের অপেক্ষায়%s।',
                        $this->num($r['count']), $this->num($r['pending']),
                        $this->f($r['pending_amount']) > 0 ? ', মোট ' . $this->m($this->f($r['pending_amount'])) : '')),
            $lines ? Phrase::join($lines, $this->L()) . $this->t('.', '।') : '',
            $this->t('Once approved, money requests are disbursed and then recovered through payslip instalments.',
                     'অনুমোদন হলে টাকার আবেদন ছাড় হয়, তারপর পে-স্লিপের কিস্তিতে আদায় হয়।'),
        ]);
    }

    /** one customer's or vendor's running account, and where it contradicts their invoices */
    private function a_party_balance(): string
    {
        $name = (string) ($this->c['slots']['name_hint'] ?? '');
        if ($name === '') {
            return $this->say([$this->open('ok'),
                $this->t('Whose account? Give me a customer or vendor name and I will read their ledger — what they were invoiced, what they have paid, and what is left.',
                         'কার হিসাব? গ্রাহক বা সরবরাহকারীর নামটা বলুন — কত বিল হয়েছে, কত দিয়েছেন, কত বাকি, সব বের করে দিচ্ছি।')]);
        }
        $this->used('get_party_ledger');
        $L = $this->A()->partyLedger($name, 12);
        $p = $L['parties'][0] ?? null;
        if (!$p) {
            return $this->say([$this->open('warn'),
                $this->t(sprintf('Nothing in the party ledger matches "%s".', $name),
                         sprintf('পার্টি খাতায় "%s" নামে কিছু পাইনি।', $name)),
                $this->t('I can search the customers, suppliers and staff by name if you want.',
                         'চাইলে গ্রাহক, সরবরাহকারী বা কর্মীদের মধ্যে নাম ধরে খুঁজে দিতে পারি।')]);
        }
        $bal = $this->f($p['balance']);
        $owes = $bal > 0;
        $head = $this->t(
            sprintf('%s was invoiced %s and has paid %s, leaving %s.', (string) $p['party_name'], $this->m($this->f($p['debit'])), $this->m($this->f($p['credit'])),
                $owes ? $this->m($bal) . ' owing' : ($bal < 0 ? $this->m(abs($bal)) . ' in credit to them' : 'nothing outstanding')),
            sprintf('%s-এর নামে বিল হয়েছে %s, দিয়েছেন %s — বাকি %s।', (string) $p['party_name'], $this->m($this->f($p['debit'])), $this->m($this->f($p['credit'])),
                $owes ? $this->m($bal) : ($bal < 0 ? $this->m(abs($bal)) . ' তাঁর জমা' : 'কিছু না')));
        $warn = !empty($p['erp_balance_disagrees'])
            ? $this->t(sprintf('The ERP stores their running balance as %s — it is written in entry order, not date order, so a back-dated payment leaves it wrong. %s is the figure to trust.',
                    $this->m($this->f($p['erp_balance'])), $this->m($bal)),
                sprintf('ইআরপি তাঁর চলতি ব্যালান্স রেখেছে %s — ওটা এন্ট্রির ক্রমে লেখা হয়, তারিখের ক্রমে নয়, তাই পিছনের তারিখের পেমেন্ট থাকলে ভুল দেখায়। ঠিক অঙ্কটা %s।',
                    $this->m($this->f($p['erp_balance'])), $this->m($bal)))
            : '';
        $last = $L['recent'][0] ?? null;
        $mv = $last
            ? $this->t(sprintf('Last movement %s: %s %s.', (string) $last['date'], (string) $last['type'],
                    $this->f($last['credit']) > 0 ? 'payment of ' . $this->m($this->f($last['credit'])) : 'charge of ' . $this->m($this->f($last['debit']))),
                sprintf('সর্বশেষ %s তারিখে: %s, %s।', (string) $last['date'], (string) $last['type'],
                    $this->f($last['credit']) > 0 ? $this->m($this->f($last['credit'])) . ' জমা' : $this->m($this->f($last['debit'])) . ' বিল'))
            : '';
        $act = $bal < 0
            ? $this->act('they are in credit — apply it against their open invoices before anyone sends a reminder.',
                         'তাঁর টাকা জমা আছে — তাগাদা পাঠানোর আগে খোলা বিলের সঙ্গে মিলিয়ে নিন।')
            : ($owes ? $this->act('this is the figure to quote when you call them.', 'ফোন করলে এই অঙ্কটাই বলবেন।') : '');
        return $this->say([$this->open($bal < 0 ? 'ok' : ($owes ? 'warn' : 'good')), $head, $warn, $mv, $act]);
    }

    private function a_evaluate_person(): string
    {
        $name = (string) ($this->c['slots']['name_hint'] ?? '');
        if ($name === '') {
            return $this->say([$this->open('ok'),
                $this->t('Give me the name and I will pull their attendance, punctuality, payslips and workload together.',
                         'নামটা বলুন — তার হাজিরা, সময়ানুবর্তিতা, পে-স্লিপ আর কাজের চাপ একসাথে বের করে দিচ্ছি।')]);
        }
        $this->used('find_employee');
        $r = $this->c['tools']->run('find_employee', ['name' => $name]);
        if (!is_array($r) || isset($r['error'])) {
            return $this->say([$this->open('warn'),
                $this->t(sprintf('I could not match "%s" to anyone on the staff list.', $name),
                         sprintf('কর্মীদের তালিকায় "%s" নামে কাউকে মেলাতে পারলাম না।', $name)),
                $this->act('try the full name as it is spelled in HR → Users.',
                           'এইচআর → ইউজার-এ যেভাবে বানান আছে, পুরো নামটা দিয়ে দেখুন।')]);
        }

        $e = $r['employee'] ?? [];
        $score = (int) ($r['score'] ?? 0);
        $grade = (string) ($r['grade'] ?? '');
        $mood = Phrase::moodHigh((float) $score, 75, 55);

        $who = $this->t(
            sprintf('%s — %s, %s.', (string) ($e['name'] ?? $name), (string) ($e['designation'] ?? ''), (string) ($e['company'] ?? '')),
            sprintf('%s — %s, %s।', (string) ($e['name'] ?? $name), (string) ($e['designation'] ?? ''), (string) ($e['company'] ?? '')));

        $head = $this->t(
            sprintf('Scores %s out of 100, grade %s, over the last %s days.', $this->num($score), $grade, $this->num($r['days'] ?? 30)),
            sprintf('গত %s দিনের হিসাবে স্কোর ১০০ তে %s, গ্রেড %s।', $this->num($r['days'] ?? 30), $this->num($score), $grade));

        $att = $this->t(
            sprintf('Attendance %s, punctuality %s — late on %s days.',
                $this->pc($this->f($r['attendance_pct'] ?? 0)), $this->pc($this->f($r['punctuality_pct'] ?? 0)), $this->num($r['late_days'] ?? 0)),
            sprintf('হাজিরা %s, সময়ানুবর্তিতা %s — %s দিন দেরি করেছে।',
                $this->pc($this->f($r['attendance_pct'] ?? 0)), $this->pc($this->f($r['punctuality_pct'] ?? 0)), $this->num($r['late_days'] ?? 0)));

        $work = ((int) ($r['tasks_done'] ?? 0) + (int) ($r['open_tasks'] ?? 0) + (int) ($r['leads_won'] ?? 0)) > 0
            ? $this->t(sprintf('%s tasks closed, %s still open, %s leads won.',
                        $this->num($r['tasks_done'] ?? 0), $this->num($r['open_tasks'] ?? 0), $this->num($r['leads_won'] ?? 0)),
                       sprintf('%s টা কাজ শেষ, %s টা এখনো খোলা, %s টা লিড জিতেছে।',
                        $this->num($r['tasks_done'] ?? 0), $this->num($r['open_tasks'] ?? 0), $this->num($r['leads_won'] ?? 0)))
            : $this->t('No task or lead activity is recorded against them, so this score rests on attendance alone.',
                       'তার নামে কোনো টাস্ক বা লিডের রেকর্ড নেই, তাই এই স্কোরটা কেবল হাজিরার ওপর দাঁড়িয়ে।');

        $good = Loc::bnAll(array_map('strval', $r['strengths'] ?? []), $this->L());
        $bad = Loc::bnAll(array_map('strval', $r['concerns'] ?? []), $this->L());

        $salary = $this->f($e['salary'] ?? 0) > 0
            ? $this->t(sprintf('On %s a month, joined %s.', $this->m($this->f($e['salary'])), Phrase::day((string) ($e['joined'] ?? ''), 'en')),
                       sprintf('বেতন মাসে %s, যোগ দিয়েছে %s।', $this->m($this->f($e['salary'])), Phrase::day((string) ($e['joined'] ?? ''), 'bn')))
            : '';

        $advice = ($r['late_days'] ?? 0) > 10
            ? $this->act('the lateness is the story here — a written warning costs nothing and the deduction only bites past two hours a month.',
                         'এখানে আসল কথা দেরিটাই — একটা লিখিত সতর্কতা খরচ ছাড়াই কাজ দেয়, আর কর্তন তো মাসে দুই ঘণ্টা পেরোলে তবেই ধরে।')
            : $this->act('nothing here needs action from you today.',
                         'আজকের দিনে এখান থেকে আপনার কিছু করার নেই।');

        return $this->say([
            $this->open($mood), $who, $head, $att, $work, $salary,
            $good ? $this->t('In their favour: ' . Phrase::join($good, 'en') . '.', 'ভালো দিক: ' . Phrase::join($good, 'bn') . '।') : '',
            $bad ? $this->t('Against: ' . Phrase::join($bad, 'en') . '.', 'দুর্বল দিক: ' . Phrase::join($bad, 'bn') . '।') : '',
            $advice,
        ]);
    }

    private function a_online_now(): string
    {
        $this->used('get_attendance_today');
        $A = $this->A();
        $now = time();
        $online = [];
        foreach ($A->rows('employees') as $e) {
            $ls = (string) ($e['last_seen_at'] ?? '');
            if ($ls === '') continue;
            if ($now - strtotime($ls) <= 300) $online[] = (string) ($e['name'] ?? '');
        }
        if (!$online) {
            return $this->say([$this->open('ok'),
                $this->t('Nobody has been seen in the last five minutes — that is the window the ERP uses for "online".',
                         'শেষ পাঁচ মিনিটে কাউকে দেখা যায়নি — ERP এই পাঁচ মিনিটকেই "অনলাইন" ধরে।')]);
        }
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('%s %s active right now: %s.', $this->num(count($online)), Phrase::plural(count($online), 'person is', 'people are'), Phrase::join(array_slice($online, 0, 8), 'en')),
                     sprintf('এই মুহূর্তে %s জন সক্রিয়: %s।', $this->num(count($online)), Phrase::join(array_slice($online, 0, 8), 'bn'))),
            $this->t('"Online" here just means seen within the last five minutes.', 'এখানে "অনলাইন" মানে শেষ পাঁচ মিনিটে দেখা গেছে, এটুকুই।'),
        ]);
    }

    /* ================= OPS ================= */

    private function a_tasks(): string
    {
        $this->used('get_tasks');
        $r = $this->A()->tasks();
        $open = (int) ($r['open'] ?? 0);
        if ($open === 0 && (int) ($r['overdue'] ?? 0) === 0) {
            return $this->say([$this->open('ok'),
                $this->t('No open tasks are reaching me. Either the boards are clear or tasks are being tracked outside the ERP.',
                         'খোলা কোনো টাস্ক আমার কাছে আসছে না। হয় বোর্ড খালি, নয় টাস্ক ERP-র বাইরে রাখা হচ্ছে।'),
                $this->act('Work → Tasks is where the boards live if you want to check.',
                           'দেখতে চাইলে বোর্ডগুলো আছে কাজ → টাস্ক-এ।')]);
        }
        $od = (int) ($r['overdue'] ?? 0);
        $mood = $od > 0 ? ($od > $open * 0.3 ? 'bad' : 'warn') : 'good';
        $ol = $r['overloaded'][0] ?? null;
        return $this->say([
            $this->open($mood),
            $this->t(sprintf('%s tasks are open and %s of them are overdue%s.', $this->num($open), $this->num($od),
                        ($r['overdue_high'] ?? 0) ? ', ' . $this->num($r['overdue_high']) . ' of those high priority' : ''),
                     sprintf('খোলা টাস্ক %s টা, তার %s টার সময় পেরিয়েছে%s।', $this->num($open), $this->num($od),
                        ($r['overdue_high'] ?? 0) ? ', এর মধ্যে %s টা উচ্চ অগ্রাধিকারের' : '')),
            ($r['closed_last_7_days'] ?? 0)
                ? $this->t(sprintf('%s were closed this week.', $this->num($r['closed_last_7_days'])),
                           sprintf('এই সপ্তাহে %s টা শেষ হয়েছে।', $this->num($r['closed_last_7_days'])))
                : '',
            $ol ? $this->t(sprintf('%s is carrying the most — %s open.', $ol['name'], $this->num($ol['open'])),
                           sprintf('সবচেয়ে বেশি চাপ %s এর ওপর — %s টা খোলা।', $ol['name'], $this->num($ol['open']))) : '',
            $od > 0 ? $this->act('reassign rather than remind — the overdue pile usually sits with one person.',
                                 'তাগাদা না দিয়ে বরং ভাগ করে দিন — বকেয়া কাজের স্তূপ সাধারণত একজনের ঘাড়েই থাকে।') : '',
        ]);
    }

    private function a_projects(): string
    {
        $this->used('get_projects');
        $r = $this->A()->projects();
        $active = (int) ($r['active'] ?? 0);
        $risk = $r['at_risk'] ?? [];
        if ($active === 0) {
            return $this->say([$this->open('ok'), $this->t('No project is currently active.', 'এই মুহূর্তে কোনো প্রকল্প চালু নেই।')]);
        }
        $mood = $risk ? (count($risk) > $active * 0.4 ? 'bad' : 'warn') : 'good';
        $w = $risk[0] ?? null;
        return $this->say([
            $this->open($mood),
            $this->t(sprintf('%s projects are running, %s of them at risk.', $this->num($active), $this->num(count($risk))),
                     sprintf('%s টা প্রকল্প চলছে, তার %s টা ঝুঁকিতে।', $this->num($active), $this->num(count($risk)))),
            $w ? $this->t(sprintf('The worst is %s — %s done with %s of its time gone.', $w['name'], $this->pc($this->f($w['progress'])), $this->pc($this->f($w['elapsed_pct']))),
                          sprintf('সবচেয়ে খারাপ অবস্থা %s এর — কাজ হয়েছে %s, অথচ সময় চলে গেছে %s।', $w['name'], $this->pc($this->f($w['progress'])), $this->pc($this->f($w['elapsed_pct'])))) : '',
            $risk ? $this->act('a project slipping this early rarely recovers on its own — move a person or move the date.',
                               'এত আগে পিছিয়ে পড়া প্রকল্প নিজে নিজে ঠিক হয় না — হয় লোক দিন, নয় তারিখ পেছান।') : '',
        ]);
    }

    private function a_todos(): string
    {
        $this->used('get_tasks');
        $todos = $this->A()->rows('office_todos');
        $openN = 0;
        foreach ($todos as $t) {
            $st = strtolower((string) ($t['status'] ?? ''));
            if ($st !== 'done' && $st !== 'completed') $openN++;
        }
        return $this->say([
            $this->open('ok'),
            $this->t(sprintf('%s office to-dos on the board, %s still open.', $this->num(count($todos)), $this->num($openN)),
                     sprintf('অফিস কাজের তালিকায় %s টা আছে, %s টা এখনো খোলা।', $this->num(count($todos)), $this->num($openN))),
            $this->t('These are the per-department checklists, separate from project tasks.',
                     'এগুলো বিভাগভিত্তিক চেকলিস্ট, প্রকল্পের টাস্ক থেকে আলাদা।'),
        ]);
    }

    /* ================= CRM ================= */

    private function a_pipeline(): string
    {
        $this->used('get_pipeline');
        $r = $this->A()->pipeline();
        $open = (int) ($r['open'] ?? 0);
        $won = (int) ($r['won'] ?? 0);
        $lost = (int) ($r['lost'] ?? 0);

        if ($open === 0 && $won === 0 && $lost === 0) {
            return $this->say([$this->open('warn'),
                $this->t('The pipeline is empty — not a single lead is recorded.', 'পাইপলাইন খালি — একটাও লিড রেকর্ড হয়নি।'),
                $this->t('That is a recording gap rather than a business one: work is clearly coming in, it just is not being entered as leads.',
                         'এটা ব্যবসার সমস্যা না, রেকর্ডের সমস্যা: কাজ তো আসছেই, কেবল লিড হিসেবে বসানো হচ্ছে না।'),
                $this->act('without leads in CRM there is no conversion rate and no forecast worth the name — that is the one habit worth fixing.',
                           'সিআরএম-এ লিড না থাকলে কনভার্শন রেটও নেই, ভালো পূর্বাভাসও নেই — এই অভ্যাসটাই আগে ঠিক করার মতো।')]);
        }
        $mood = ($r['stale_count'] ?? 0) > 0 ? 'warn' : 'ok';
        return $this->say([
            $this->open($mood),
            $this->t(sprintf('%s open leads worth %s; %s won and %s lost%s.',
                        $this->num($open), $this->m($this->f($r['open_value'] ?? 0)), $this->num($won), $this->num($lost),
                        $r['conversion_pct'] !== null ? ' — ' . $this->pc($this->f($r['conversion_pct'])) . ' conversion' : ''),
                     sprintf('খোলা লিড %s টা, মূল্য %s; জেতা %s, হারা %s%s।',
                        $this->num($open), $this->m($this->f($r['open_value'] ?? 0)), $this->num($won), $this->num($lost),
                        $r['conversion_pct'] !== null ? ' — কনভার্শন ' . $this->pc($this->f($r['conversion_pct'])) : '')),
            ($r['stale_count'] ?? 0)
                ? $this->t(sprintf('%s have gone cold and %s follow-ups are due today.', $this->num($r['stale_count']), $this->num($r['followups_today'] ?? 0)),
                           sprintf('%s টা ঠান্ডা হয়ে গেছে আর আজ %s টা ফলোআপ দেওয়ার কথা।', $this->num($r['stale_count']), $this->num($r['followups_today'] ?? 0)))
                : '',
            $this->t('A lead runs new, contacted, qualified, proposal sent, negotiation, then won or lost — and a won interior lead becomes a project on its own.',
                     'লিড চলে এই ধাপে: new, contacted, qualified, proposal sent, negotiation, তারপর won বা lost — আর ইন্টেরিয়রের লিড জিতলে নিজেই প্রকল্প হয়ে যায়।'),
        ]);
    }

    private function a_customers(): string { return $this->partyAnswer('customer'); }
    private function a_suppliers(): string { return $this->partyAnswer('supplier'); }

    private function partyAnswer(string $which): string
    {
        $this->used('get_parties');
        $r = $this->I()->parties($which, $this->c['slots']['top'] ?: 6);
        $isCust = $which === 'customer';
        if (($r['count'] ?? 0) === 0) {
            return $this->say([$this->open('warn'),
                $isCust ? $this->t('No customer is on the master list.', 'গ্রাহকের তালিকায় কেউ নেই।')
                        : $this->t('No supplier is on the master list.', 'সরবরাহকারীর তালিকায় কেউ নেই।')]);
        }
        $withBal = (int) ($r['with_balance'] ?? 0);
        $names = array_map(fn($x) => $x['name'] . ($this->f($x['balance']) > 0 ? ' (' . $this->m($this->f($x['balance'])) . ')' : ''), $r['rows']);

        return $this->say([
            $this->open('ok'),
            $isCust
                ? $this->t(sprintf('%s customers on file, %s of them carrying a balance.', $this->num($r['count']), $this->num($withBal)),
                           sprintf('তালিকায় %s জন গ্রাহক, তাদের %s জনের কাছে ব্যালেন্স আছে।', $this->num($r['count']), $this->num($withBal)))
                : $this->t(sprintf('%s suppliers on file, %s of them carrying a balance.', $this->num($r['count']), $this->num($withBal)),
                           sprintf('তালিকায় %s জন সরবরাহকারী, তাদের %s জনের ব্যালেন্স আছে।', $this->num($r['count']), $this->num($withBal))),
            Phrase::join($names, $this->L()) . $this->t('.', '।'),
            $withBal === 0
                ? $this->t('Every balance reads zero because the postings are not tagged to a party — the ledger knows the amount but not the name.',
                           'সব ব্যালেন্স শূন্য দেখাচ্ছে কারণ এন্ট্রিগুলোতে পার্টি ট্যাগ করা নেই — খতিয়ান অঙ্কটা জানে, নামটা জানে না।')
                : '',
            $this->act('Accounts → Party Statement gives one party\'s full running account.',
                       'একজন পার্টির পুরো চলতি হিসাব আছে হিসাব → পার্টি স্টেটমেন্ট-এ।'),
        ]);
    }

    /* ================= META ================= */

    /** the ERP's own source is the authority on addresses; Kb supplies the words */
    private function erpScreen(string $query): ?array
    {
        if (!class_exists('ErpMap') || !method_exists('ErpMap', 'find')) return null;
        try {
            if (method_exists('ErpMap', 'available') && !ErpMap::available()) return null;
            $hits = ErpMap::find($query, 3);
        } catch (Throwable $e) {
            return null;
        }
        if (!is_array($hits) || !$hits) return null;
        $top = $hits[0];
        $uri = (string) ($top['uri'] ?? '');
        if ($uri === '') return null;
        $role = (method_exists('ErpMap', 'role') ? (string) ErpMap::role() : '') ?: Kb::ROLE;
        return [
            'url' => str_replace('{role}', $role, $uri),
            'label' => (string) ($top['label'] ?? ''),
            'section' => (string) ($top['section'] ?? ''),
        ];
    }

    private function a_navigation(): string
    {
        $topic = $this->c['slots']['topic'] ?? null;
        $kb = Kb::findScreen($this->c['norm'], $topic);

        // ask the generated map with the boss's own words, then with the topic we inferred
        $live = $this->erpScreen($this->c['q']);
        if ($live === null && $kb) $live = $this->erpScreen((string) $kb['path']);

        if (!$kb && !$live) {
            return $this->say([$this->open('ok'),
                $this->t('Tell me what you are looking for — payslips, attendance, expenses, the ledger, payment schedules, ticket or visa sales, leads, projects — and I will give you the exact menu path.',
                         'কী খুঁজছেন বলুন — পে-স্লিপ, হাজিরা, খরচ, খতিয়ান, পেমেন্ট সূচি, টিকিট বা ভিসা সেলস, লিড, প্রকল্প — আমি ঠিক মেনুর পথটা বলে দেব।')]);
        }

        $where = '';
        if ($live !== null) {
            $named = $live['label'] !== '' ? $live['label'] : ($kb ? $kb['en_menu'] : '');
            $sect = $live['section'] !== '' ? $live['section'] : '';
            $where = $this->t(
                ($sect !== '' ? $sect . ' → ' : '') . ($named !== '' ? $named : 'that screen') . ' — ' . $live['url'] . '.',
                ($sect !== '' ? $sect . ' → ' : '') . ($named !== '' ? $named : 'ওই স্ক্রিন') . ' — ' . $live['url'] . '।');
        } elseif ($kb) {
            $where = $this->t($kb['en_menu'] . ' — ' . $kb['url'] . '.', $kb['bn_menu'] . ' — ' . $kb['url'] . '।');
        }

        return $this->say([
            $where,
            $kb ? $this->t('That screen is ' . $kb['en'] . '.', 'ওই স্ক্রিনে আছে ' . $kb['bn'] . '।') : '',
            $this->t('You can also just ask me the number and skip the screen entirely.',
                     'অবশ্য সংখ্যাটা আমাকেই জিজ্ঞেস করতে পারেন, স্ক্রিনে যাওয়ার দরকার নেই।'),
        ]);
    }

    private function a_howto(): string
    {
        $topic = $this->c['slots']['topic'] ?? null;
        $r = Kb::findRule($this->c['norm'], $topic);
        if ($r) {
            return $this->say([$this->open('ok'), (string) $r[$this->L()]]);
        }
        $s = Kb::findScreen($this->c['norm'], $topic);
        if ($s) return $this->a_navigation();
        return $this->say([$this->open('ok'),
            $this->t('Ask me about a specific rule — how late deduction works, how an expense posts, how payroll is calculated, how a leave balance is worked out, or what happens when a payment schedule is approved.',
                     'নির্দিষ্ট কোনো নিয়ম জিজ্ঞেস করুন — দেরির কর্তন কিভাবে হয়, খরচের এন্ট্রি কোথায় বসে, বেতন কিভাবে হিসাব হয়, ছুটির হিসাব কিভাবে হয়, বা পেমেন্ট সূচি অনুমোদন হলে কী হয়।')]);
    }

    private function a_capabilities(): string
    {
        return $this->say([
            $this->t('I read the live ERP, so ask me anything with a number behind it.',
                     'আমি সরাসরি ERP পড়ি, তাই যার পেছনে একটা সংখ্যা আছে — যা খুশি জিজ্ঞেস করুন।'),
            $this->t('Money: cash, bank balances, receivables and payables with aging, profit and loss, the balance sheet, trial balance, any account ledger by code, expenses by category, budget variance, loans, advances, runway and a forecast.',
                     'টাকা: নগদ, ব্যাংক ব্যালেন্স, পাওনা-দেনা aging সহ, লাভ-লোকসান, স্থিতিপত্র, রেওয়ামিল, কোড দিয়ে যেকোনো খতিয়ান, খাত অনুযায়ী খরচ, বাজেটের সাথে ফারাক, ঋণ, অগ্রিম, কতদিন চলবে আর পূর্বাভাস।'),
            $this->t('People: today\'s attendance, who is late, who is chronically late, payroll for any month, one person\'s payslip by name, leave, holidays, headcount by department, staff requests.',
                     'মানুষ: আজকের হাজিরা, কে দেরি করল, কে নিয়মিত দেরি করে, যেকোনো মাসের পে-রোল, নাম ধরে একজনের পে-স্লিপ, ছুটি, সরকারি ছুটি, বিভাগ অনুযায়ী জনবল, কর্মীদের আবেদন।'),
            $this->t('Work: tasks, projects at risk, the CRM pipeline, customers and suppliers. And the ERP itself — where a screen lives, or how a rule actually works.',
                     'কাজ: টাস্ক, ঝুঁকিতে থাকা প্রকল্প, সিআরএম পাইপলাইন, গ্রাহক আর সরবরাহকারী। আর ERP নিজেই — কোন স্ক্রিন কোথায়, বা কোন নিয়ম আসলে কিভাবে কাজ করে।'),
            $this->t('Bangla or English, whichever you type in.', 'বাংলা বা ইংরেজি — যেটায় লিখবেন, সেটাতেই উত্তর দেব।'),
        ]);
    }

    private function a_greeting(): string
    {
        $hour = (int) date('G');
        $greet = $this->bn()
            ? ($hour < 12 ? 'শুভ সকাল, স্যার।' : ($hour < 17 ? 'শুভ অপরাহ্ন, স্যার।' : 'শুভ সন্ধ্যা, স্যার।'))
            : ($hour < 12 ? 'Good morning.' : ($hour < 17 ? 'Good afternoon.' : 'Good evening.'));

        $norm = $this->c['norm'];
        if (strpos($norm, 'thank') !== false || mb_strpos($norm, 'ধন্যবাদ') !== false) {
            return $this->t('Any time. Ask me whenever you want a number checked.',
                            'যেকোনো সময়। কোনো হিসাব দেখতে চাইলে বলবেন।');
        }
        $k = $this->A()->kpis();
        return $this->say([
            $greet,
            $this->t(sprintf('Cash is at %s and nothing has broken overnight.', $this->m($this->f($k['cash'] ?? 0))),
                     sprintf('হাতে আছে %s, আর রাতারাতি কিছু ভাঙেনি।', $this->m($this->f($k['cash'] ?? 0)))),
            $this->t('Ask me for the brief when you want the full picture.',
                     'পুরো ছবিটা চাইলে ব্রিফ চাইবেন।'),
        ]);
    }

    /* ================= fallback ================= */

    private function fallback(array $parse): string
    {
        // a screen or a rule may still answer even when no intent scored
        $s = Kb::findScreen($this->c['norm'], null);
        $r = Kb::findRule($this->c['norm'], null);
        if ($r) return $this->say([$this->open('ok'), (string) $r[$this->L()]]);
        if ($s) return $this->a_navigation();

        $k = $this->A()->kpis();
        $alts = $parse['alternatives'] ?? [];
        $hint = '';
        if ($alts) {
            $names = array_map(fn($a) => str_replace('_', ' ', $a), array_slice($alts, 0, 2));
            $hint = $this->t('Did you mean ' . Phrase::join($names, 'en') . '?',
                             Phrase::join($names, 'bn') . ' — এটা জানতে চাইছেন?');
        }
        return $this->say([
            $this->t('I did not catch which report that maps to.', 'কোন হিসাবটা চাইছেন সেটা ধরতে পারিনি।'),
            $hint,
            $this->t(sprintf('Where things stand: cash %s, %s absent today, %s tasks overdue.',
                        $this->m($this->f($k['cash'] ?? 0)), $this->num($k['absent_today'] ?? 0), $this->num($k['tasks_overdue'] ?? 0)),
                     sprintf('এই মুহূর্তের অবস্থা: হাতে %s, আজ %s জন অনুপস্থিত, %s টা কাজ বকেয়া।',
                        $this->m($this->f($k['cash'] ?? 0)), $this->num($k['absent_today'] ?? 0), $this->num($k['tasks_overdue'] ?? 0))),
            $this->t('Try: cash, receivables, payables, profit, expenses by category, payroll, attendance, who came late, leave, pipeline, tasks, projects, approvals, a person by name, or where a screen lives.',
                     'বলে দেখুন: ক্যাশ, পাওনা, দেনা, লাভ, খাত অনুযায়ী খরচ, বেতন, হাজিরা, কে দেরি করল, ছুটি, পাইপলাইন, টাস্ক, প্রকল্প, অনুমোদন, কারও নাম, বা কোন স্ক্রিন কোথায়।'),
        ]);
    }
}
