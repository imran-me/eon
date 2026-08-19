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
    /** a date or YYYY-MM in the answer language — digits only, separators kept */
    private function d(?string $v): string
    {
        $v = (string) $v;
        return $this->bn() ? str_replace(['0','1','2','3','4','5','6','7','8','9'], ['০','১','২','৩','৪','৫','৬','৭','৮','৯'], $v) : $v;
    }

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

    /* ================= WHY ================= */

    private function a_why(): string
    {
        if (!class_exists('Reason')) return $this->a_risks();

        // "why was this deducted" is asking for the rule, not for a trend —
        // the rule is the honest answer, so hand it over.
        $ruleWords = ['deduction', 'deducted', 'late rule', 'grace', 'overtime',
                      'কর্তন', 'কাটা', 'কেটে', 'নিয়ম', 'ওভারটাইম'];
        foreach ($ruleWords as $w) {
            if (mb_strpos($this->c['norm'], $w) !== false) {
                $r = Kb::findRule($this->c['norm'], $this->c['slots']['topic'] ?? null);
                if ($r) {
                    return $this->say([$this->open('ok'), (string) $r[$this->L()],
                        $this->act('give me the name and the month if a figure still looks wrong, and I will read the actual slip.',
                                   'কোনো অঙ্ক এখনো ভুল লাগলে নাম আর মাসটা বলুন, আমি আসল স্লিপটা পড়ে দেব।')]);
                }
            }
        }

        $this->used('explain_why');
        $R = new Reason($this->A());
        $topic = $R->topicOf($this->c['norm'], $this->c['slots']['topic'] ?? null);
        $x = $R->explain($topic);
        $ev = [];
        foreach (($x['evidence'] ?? []) as $e) {
            $line = $this->evidenceLine($e, $topic);
            if ($line !== '') $ev[] = $line;
        }
        if (!$ev) return $this->a_risks();

        $mood = in_array($x['claim'] ?? '', ['loss', 'tight'], true) ? 'bad' : 'ok';
        $head = $this->whyHead($topic, $x);
        $act = $this->whyAction($topic, (string) ($x['cause'] ?? ''));
        $caveat = ($x['confidence'] ?? '') === 'thin'
            ? $this->t('That is on a short history, so read it as a direction rather than a measurement.',
                       'হিসাবটা অল্প সময়ের ওপর, তাই একে মাপ না ধরে দিক ধরুন।')
            : '';

        return $this->say(array_merge([$this->open($mood), $head], $ev, [$caveat, $act]));
    }

    private function whyHead(string $topic, array $x): string
    {
        $net = $this->f($x['net'] ?? 0);
        switch ($topic) {
            case 'profit':
                return ($x['claim'] ?? '') === 'loss'
                    ? $this->t(sprintf('%s closed at a loss of %s. Here is where it went.', Phrase::monthName((string) ($x['month'] ?? ''), 'en'), $this->m(abs($net))),
                               sprintf('%s মাসে লোকসান %s। কোথায় গেল, দেখুন।', Phrase::monthName((string) ($x['month'] ?? ''), 'bn'), $this->m(abs($net))))
                    : $this->t(sprintf('%s closed in profit at %s. What carried it:', Phrase::monthName((string) ($x['month'] ?? ''), 'en'), $this->m($net)),
                               sprintf('%s মাসে মুনাফা %s। কীসে হলো:', Phrase::monthName((string) ($x['month'] ?? ''), 'bn'), $this->m($net)));
            case 'cash':
                return $this->t('The cash position, and what is pulling on it:', 'হাতের টাকার অবস্থা, আর কীসে টান পড়ছে:');
            case 'spend':
                return $this->t('Where the spending actually sits:', 'খরচটা আসলে কোথায় বসে আছে:');
            case 'receivable':
                return $this->t('What is owed to you, and why it is not in:', 'আপনার পাওনা কত, আর কেন আসছে না:');
            case 'payable':
                return $this->t('What you owe, and what is behind it:', 'আপনার দেনা কত, আর তার পেছনে কী:');
            case 'attendance':
                return $this->t('The attendance picture, and what drives it:', 'হাজিরার ছবি, আর তার পেছনের কারণ:');
            case 'payroll':
                return $this->t('The salary bill, and what makes it that size:', 'বেতনের অঙ্ক, আর সেটা এত কেন:');
            case 'delivery':
                return $this->t('Where delivery stands, and what is slipping:', 'কাজের অবস্থা, আর কোথায় পিছিয়ে যাচ্ছে:');
        }
        return '';
    }

    /** one line of the argument */
    private function evidenceLine(array $e, string $topic): string
    {
        $f = (string) ($e['fact'] ?? '');
        $parts = [
            'net' => fn() => $this->t(
                sprintf('Income %s, direct cost %s, overheads %s.', $this->m($this->f($e['detail']['income'] ?? 0)), $this->m($this->f($e['detail']['direct'] ?? 0)), $this->m($this->f($e['detail']['opex'] ?? 0))),
                sprintf('আয় %s, সরাসরি খরচ %s, সাধারণ খরচ %s।', $this->m($this->f($e['detail']['income'] ?? 0)), $this->m($this->f($e['detail']['direct'] ?? 0)), $this->m($this->f($e['detail']['opex'] ?? 0)))),

            'mover' => function () use ($e) {
                $names = ['income' => ['income', 'আয়'], 'direct' => ['direct cost', 'সরাসরি খরচ'], 'opex' => ['overheads', 'সাধারণ খরচ']];
                $n = $names[(string) $e['part']] ?? ['it', 'এটা'];
                $ch = $this->f($e['change']);
                $dir = $ch >= 0 ? ['rose', 'বেড়েছে'] : ['fell', 'কমেছে'];
                return $this->t(sprintf('The part that moved is %s: it %s by %s against %s.', $n[0], $dir[0], $this->m(abs($ch)), Phrase::monthName((string) $e['from'], 'en')),
                                sprintf('যেটা নড়েছে সেটা %s: %s মাসের তুলনায় %s %s।', $n[1], Phrase::monthName((string) $e['from'], 'bn'), $this->m(abs($ch)), $dir[1]));
            },

            'opex_exceeds_income' => fn() => $this->t(
                sprintf('Overheads alone are %s times the income for the month — the loss is structural, not a pricing problem.', $this->num($e['ratio'])),
                sprintf('একা সাধারণ খরচই মাসের আয়ের %s গুণ — লোকসানটা কাঠামোগত, দামের সমস্যা না।', $this->num($e['ratio']))),

            'top_category' => fn() => $this->t(
                sprintf('Of the expenses actually booked (%s), %s is the largest at %s.', $this->m($this->f($e['booked_total'])), $e['category'], $this->m($this->f($e['amount']))),
                sprintf('যেসব খরচ আসলে বসেছে (%s), তার মধ্যে সবচেয়ে বড় %s — %s।', $this->m($this->f($e['booked_total'])), $e['category'], $this->m($this->f($e['amount'])))),

            'payroll_share' => fn() => $this->t(
                sprintf('Payroll is the real overhead: %s gross, about %s%% of it.', $this->m($this->f($e['gross'])), $this->num($e['share'])),
                sprintf('আসল সাধারণ খরচ হলো বেতন: গ্রস %s, যা এর প্রায় %s%%।', $this->m($this->f($e['gross'])), $this->num($e['share']))),

            'unapproved' => fn() => $this->t(
                sprintf('And %s items worth %s have not been approved yet, so they are not in these numbers at all.', $this->num($e['count']), $this->m($this->f($e['amount']))),
                sprintf('আর %s টা বিষয়, মোট %s, এখনো অনুমোদন হয়নি — মানে এই হিসাবে ওগুলো নেইই।', $this->num($e['count']), $this->m($this->f($e['amount'])))),

            'position' => function () use ($e, $topic) {
                if ($topic === 'cash') {
                    return $this->t(sprintf('Cash %s against a burn of %s a month — about %s months, and %s%% of that burn is payroll.',
                            $this->m($this->f($e['cash'])), $this->m($this->f($e['burn'])), $this->num($e['months']), $this->num($e['payroll_share'])),
                        sprintf('হাতে %s, মাসে খরচ %s — প্রায় %s মাস চলবে, আর ওই খরচের %s%% ই বেতন।',
                            $this->m($this->f($e['cash'])), $this->m($this->f($e['burn'])), $this->num($e['months']), $this->num($e['payroll_share'])));
                }
                return $this->t(sprintf('%s open, %s of it overdue across %s items.', $this->m($this->f($e['total'])), $this->m($this->f($e['overdue'])), $this->num($e['count'])),
                                sprintf('খোলা আছে %s, তার %s বকেয়া, %s টা বিষয়ে।', $this->m($this->f($e['total'])), $this->m($this->f($e['overdue'])), $this->num($e['count'])));
            },

            'money_owed_in' => fn() => $this->t(
                sprintf('%s is owed to you and all of %s is past due — %s alone owes %s.', $this->m($this->f($e['total'])), $this->m($this->f($e['overdue'])), (string) $e['top'], $this->m($this->f($e['top_amount']))),
                sprintf('আপনার পাওনা %s, তার %s তারিখ পেরিয়েছে — একা %s এর কাছেই %s।', $this->m($this->f($e['total'])), $this->m($this->f($e['overdue'])), (string) $e['top'], $this->m($this->f($e['top_amount'])))),

            'money_owed_out' => fn() => $this->t(
                sprintf('Against that you owe %s, with %s already overdue.', $this->m($this->f($e['total'])), $this->m($this->f($e['overdue']))),
                sprintf('তার বিপরীতে আপনার দেনা %s, যার %s ইতিমধ্যে বকেয়া।', $this->m($this->f($e['total'])), $this->m($this->f($e['overdue'])))),

            'shortfall' => fn() => ($e['covered_by_receivables'] ?? false)
                ? $this->t(sprintf('So the gap is %s, and collecting everything overdue would close it.', $this->m($this->f($e['gap']))),
                           sprintf('ফলে ঘাটতি %s, আর সব বকেয়া আদায় করতে পারলে সেটা মিটে যাবে।', $this->m($this->f($e['gap']))))
                : $this->t(sprintf('So the gap is %s, and collecting every overdue taka would still not close it.', $this->m($this->f($e['gap']))),
                           sprintf('ফলে ঘাটতি %s, আর সব বকেয়া আদায় করলেও সেটা মিটবে না।', $this->m($this->f($e['gap'])))),

            'category' => fn() => $this->t(
                sprintf('%s %s (%s).', $e['category'], $this->m($this->f($e['amount'])), $this->pc($this->f($e['pct']))),
                sprintf('%s %s (%s)।', $e['category'], $this->m($this->f($e['amount'])), $this->pc($this->f($e['pct'])))),

            'spike' => fn() => $this->t(
                sprintf('%s is running %s times its own average this month.', $e['category'], $this->num($e['times'])),
                sprintf('%s খাত এ মাসে নিজের গড়ের %s গুণ চলছে।', $e['category'], $this->num($e['times']))),

            'payroll_dominates' => fn() => $this->t(
                sprintf('Booked expenses are only %s — the money really goes out as salary, %s of it.', $this->m($this->f($e['expenses'])), $this->m($this->f($e['gross']))),
                sprintf('খাতায় বসা খরচ মাত্র %s — টাকা আসলে বেরোয় বেতন হয়ে, %s।', $this->m($this->f($e['expenses'])), $this->m($this->f($e['gross'])))),

            'party' => fn() => $this->t(
                sprintf('%s owes %s, oldest %s days.', (string) $e['name'], $this->m($this->f($e['due'])), $this->num($e['oldest'])),
                sprintf('%s এর কাছে %s, সবচেয়ে পুরনোটা %s দিনের।', (string) $e['name'], $this->m($this->f($e['due'])), $this->num($e['oldest']))),

            'no_schedules' => fn() => $this->t(
                'Nothing shows as receivable because sales are booked without a payment schedule — the money may well be owed, the ERP simply cannot see it.',
                'পাওনা কিছু দেখাচ্ছে না কারণ বিক্রি বসছে পেমেন্ট সূচি ছাড়াই — টাকা পাওনা থাকতেই পারে, ERP শুধু সেটা দেখতে পাচ্ছে না।'),

            'oldest' => fn() => $this->t(
                sprintf('The oldest is %s at %s days (%s).', (string) $e['party'], $this->num($e['days']), (string) $e['ref']),
                sprintf('সবচেয়ে পুরনোটা %s — %s দিন (%s)।', (string) $e['party'], $this->num($e['days']), (string) $e['ref'])),

            'salary_inside' => fn() => $this->t(
                sprintf('%s of that is staff salary, which is the part that costs you people rather than goodwill.', $this->m($this->f($e['amount']))),
                sprintf('তার %s হলো কর্মীদের বেতন — এই অংশটা সুনাম না, লোক হারায়।', $this->m($this->f($e['amount'])))),

            'today' => function () use ($e) {
                if ($e['weekend'] ?? false) return $this->t('It is the weekend.', 'আজ সাপ্তাহিক ছুটি।');
                if ((int) $e['present'] === 0 && (int) $e['absent'] === 0) return $this->t('Today has not been punched yet.', 'আজকের হাজিরা এখনো ওঠেনি।');
                return $this->t(sprintf('Today %s of %s are in.', $this->num($e['present']), $this->num($e['total'])),
                                sprintf('আজ %s জনের মধ্যে %s জন এসেছে।', $this->num($e['total']), $this->num($e['present'])));
            },

            'chronic' => fn() => $this->t(
                sprintf('%s people are late on most days; the worst is %s at %s days and %s minutes.', $this->num($e['count']), (string) $e['worst'], $this->num($e['days']), $this->num($e['minutes'])),
                sprintf('%s জন প্রায় প্রতিদিনই দেরি করে; সবচেয়ে খারাপ %s — %s দিন, %s মিনিট।', $this->num($e['count']), (string) $e['worst'], $this->num($e['days']), $this->num($e['minutes']))),

            'rule' => fn() => $this->t(
                'None of it costs anyone money until they pass two hours of lateness in the month, which is why a warning works better than the deduction.',
                'মাসে দুই ঘণ্টা দেরি না পেরোলে কারও টাকা কাটে না — এজন্যই কর্তনের চেয়ে সতর্কীকরণ ভালো কাজ করে।'),

            'coverage' => function () use ($e, $topic) {
                if ($topic === 'payroll') {
                    return $this->t(sprintf('Note the gap: %s payslips were generated against %s active staff.', $this->num($e['slips']), $this->num($e['active'])),
                                    sprintf('একটা ফাঁক খেয়াল করুন: %s জন সক্রিয় কর্মীর বিপরীতে স্লিপ হয়েছে %s টা।', $this->num($e['active']), $this->num($e['slips'])));
                }
                return $this->t(sprintf('And only %s of %s staff are on the attendance device, so this is a partial picture.', $this->num($e['tracked']), $this->num($e['active'])),
                                sprintf('আর %s জনের মধ্যে মাত্র %s জন হাজিরা ডিভাইসে আছে, তাই ছবিটা আংশিক।', $this->num($e['active']), $this->num($e['tracked'])));
            },

            'run' => fn() => $this->t(
                sprintf('%s: %s payslips, %s gross, %s net, %s unpaid.', Phrase::monthName((string) $e['month'], 'en'), $this->num($e['heads']), $this->m($this->f($e['gross'])), $this->m($this->f($e['net'])), $this->num($e['pending'])),
                sprintf('%s: %s টা স্লিপ, গ্রস %s, নিট %s, %s জনের বাকি।', Phrase::monthName((string) $e['month'], 'bn'), $this->num($e['heads']), $this->m($this->f($e['gross'])), $this->m($this->f($e['net'])), $this->num($e['pending']))),

            'headcount' => fn() => $this->t(
                sprintf('%s people are active, carrying %s of salary a month.', $this->num($e['active']), $this->m($this->f($e['monthly_salary']))),
                sprintf('সক্রিয় কর্মী %s জন, মাসে বেতন %s।', $this->num($e['active']), $this->m($this->f($e['monthly_salary'])))),

            'biggest_department' => fn() => strtolower((string) $e['department']) === 'unassigned'
                ? $this->t(sprintf('%s of them have no department recorded, which is a gap in the records.', $this->num($e['headcount'])),
                           sprintf('তাদের %s জনের কোনো বিভাগ বসানো নেই — এটা রেকর্ডের ফাঁক।', $this->num($e['headcount'])))
                : $this->t(sprintf('The heaviest department is %s with %s people.', (string) $e['department'], $this->num($e['headcount'])),
                           sprintf('সবচেয়ে ভারী বিভাগ %s — %s জন।', (string) $e['department'], $this->num($e['headcount']))),

            'tasks' => fn() => $this->t(
                sprintf('%s tasks open, %s overdue, %s closed this week.', $this->num($e['open']), $this->num($e['overdue']), $this->num($e['closed_week'])),
                sprintf('খোলা কাজ %s টা, সময় পেরিয়েছে %s টার, এই সপ্তাহে শেষ হয়েছে %s টা।', $this->num($e['open']), $this->num($e['overdue']), $this->num($e['closed_week']))),

            'projects' => fn() => $this->t(
                sprintf('%s projects active, %s at risk.', $this->num($e['active']), $this->num($e['at_risk'])),
                sprintf('চালু প্রকল্প %s টা, ঝুঁকিতে %s টা।', $this->num($e['active']), $this->num($e['at_risk']))),

            'worst' => fn() => $this->t(
                sprintf('The worst is %s — %s done with %s of its time spent.', (string) $e['name'], $this->pc($this->f($e['progress'])), $this->pc($this->f($e['elapsed']))),
                sprintf('সবচেয়ে খারাপ %s — কাজ %s, অথচ সময় গেছে %s।', (string) $e['name'], $this->pc($this->f($e['progress'])), $this->pc($this->f($e['elapsed'])))),

            'overloaded' => fn() => $this->t(
                sprintf('%s is carrying %s open items on their own.', (string) $e['name'], $this->num($e['open'])),
                sprintf('একা %s এর ঘাড়ে %s টা খোলা কাজ।', (string) $e['name'], $this->num($e['open']))),
        ];

        return isset($parts[$f]) ? ($parts[$f])() : '';
    }

    private function whyAction(string $topic, string $cause): string
    {
        $map = [
            'profit:opex'    => ['the overhead line is the lever, not price — start with payroll cover and the unapproved queue.', 'হাতিয়ার সাধারণ খরচ, দাম না — বেতনের ভার আর অনুমোদনের সারি দিয়ে শুরু করুন।'],
            'profit:income'  => ['income is what moved — look at what did not close rather than at cost.', 'নড়েছে আয় — খরচ না দেখে দেখুন কোনটা বন্ধ হয়নি।'],
            'profit:direct'  => ['direct cost moved — check purchase and vendor pricing on the recent files.', 'সরাসরি খরচ নড়েছে — সাম্প্রতিক ফাইলগুলোর ক্রয় আর ভেন্ডরের দাম দেখুন।'],
            'cash:payables'  => ['you cannot pay your way out of this one — collections first, then reschedule the rest.', 'এটা টাকা দিয়ে সমাধান হবে না — আগে আদায়, তারপর বাকিগুলোর তারিখ পেছান।'],
            'cash:collection'=> ['chase the largest overdue party this week; that single call is worth the rest together.', 'এই সপ্তাহে সবচেয়ে বড় বকেয়া পার্টিকে ধরুন; ওই একটা ফোনই বাকি সব মিলিয়ে সমান।'],
            'cash:burn'      => ['nothing is bleeding — keep an eye on the next seven days of dues.', 'কোথাও রক্তক্ষরণ নেই — সামনের সাত দিনের দেনায় চোখ রাখুন।'],
            'spend:category' => ['cap the biggest head in Expenses → Budget Setup; without a budget nothing can be called overspending.', 'খরচ → বাজেট সেটআপে সবচেয়ে বড় খাতে সীমা বসান; বাজেট না থাকলে কোনটা বেশি সেটাই বলা যায় না।'],
            'receivable:not_recorded' => ['raise a payment schedule at the point of sale, or no report will ever show what you are owed.', 'বিক্রির সময়েই পেমেন্ট সূচি খুলুন, নইলে আপনার পাওনা কোনো রিপোর্টেই উঠবে না।'],
            'receivable:collection'   => ['send the reminder to the top two names today.', 'উপরের দুইজনকে আজই তাগাদা পাঠান।'],
            'payable:salary' => ['release the salaries before the suppliers — late pay costs people, late suppliers cost patience.', 'সরবরাহকারীদের আগে বেতন ছাড়ুন — দেরিতে বেতন দিলে লোক যায়, সরবরাহকারী শুধু ধৈর্য হারায়।'],
            'payable:supplier' => ['clear the oldest first; age is what turns a payable into a phone call.', 'সবচেয়ে পুরনোটা আগে মেটান; দেনা পুরনো হলেই ফোন আসে।'],
            'attendance:habit' => ['a written warning to the worst two, and fix the device coverage — you cannot manage what only a fifth of the staff is on.', 'সবচেয়ে খারাপ দুজনকে লিখিত সতর্কতা, আর ডিভাইসের কভারেজ ঠিক করুন — এক-পঞ্চমাংশ কর্মীর হাজিরা দিয়ে ব্যবস্থাপনা হয় না।'],
            'attendance:none'  => ['nothing to act on here today.', 'আজ এখানে করার কিছু নেই।'],
            'payroll:headcount' => ['the gap between active staff and generated payslips is the thing to fix first.', 'সক্রিয় কর্মী আর তৈরি হওয়া স্লিপের ফারাকটাই আগে ঠিক করার জিনিস।'],
            'delivery:schedule' => ['re-baseline the worst project or add a person; it will not recover on its own.', 'সবচেয়ে খারাপ প্রকল্পের সময় নতুন করে ঠিক করুন বা লোক দিন; নিজে থেকে ঠিক হবে না।'],
            'delivery:none'  => ['delivery is holding.', 'কাজের দিকটা ধরে আছে।'],
        ];
        $k = $topic . ':' . $cause;
        if (isset($map[$k])) return $this->act($map[$k][0], $map[$k][1]);
        return $this->act('ask me for the piece you want to go deeper on.',
                          'কোন অংশে আরও গভীরে যেতে চান, বলুন।');
    }

    /* ================= IS THE BOOKKEEPING SOUND ================= */

    private function a_accounts_error(): string { return $this->ledgerReport(false); }
    private function a_fix(): string { return $this->ledgerReport(true); }

    /**
     * $remedyFirst — "how do I solve that" wants the fix, not the diagnosis again.
     * Re-checking rather than remembering means the answer is right even when the
     * question arrives cold, in a new session, or after something has been fixed.
     */
    private function ledgerReport(bool $remedyFirst): string
    {
        if (!method_exists($this->I(), 'ledgerErrors')) return $this->a_anomalies();
        $this->used('check_accounts');
        $r = $this->I()->ledgerErrors();

        if (($r['count'] ?? 0) === 0) {
            return $this->say([$this->open('good'),
                $this->t('The books check out. I tested the trial balance, sales against the ledger, payslip arithmetic, shared-account scope, negative balances, duplicate expenses and receivables with no schedule.',
                         'হিসাব ঠিক আছে। আমি দেখেছি রেওয়ামিল, বিক্রির সাথে খতিয়ানের মিল, পে-স্লিপের অঙ্ক, শেয়ার্ড হিসাবের স্কোপ, ঋণাত্মক ব্যালেন্স, ডুপ্লিকেট খরচ আর সূচিহীন পাওনা।')]);
        }

        $lines = [];
        $fixes = [];
        foreach ($r['items'] as $x) {
            $l = $this->errorLine($x);
            if ($l !== '') $lines[] = $l;
            $f = $this->errorFix((string) ($x['fix'] ?? ''));
            if ($f !== '' && !in_array($f, $fixes, true)) $fixes[] = $f;
        }

        $head = $this->t(
            sprintf('%s %s that need an accountant, not a manager.', $this->num($r['count']),
                Phrase::plural((int) $r['count'], 'thing', 'things')),
            sprintf('%s টা বিষয় আছে যেগুলো ম্যানেজার না, হিসাবরক্ষকের দেখা দরকার।', $this->num($r['count'])));

        $fixHead = $this->t('How to put each right:', 'কোনটা কিভাবে ঠিক করবেন:');

        if ($remedyFirst) {
            return $this->say(array_merge([$this->open('bad'), $fixHead], $fixes,
                [$this->t('That is against these findings:', 'এগুলোর বিপরীতে:')], $lines));
        }
        return $this->say(array_merge([$this->open('bad'), $head], $lines, [$fixHead], $fixes));
    }

    private function errorLine(array $x): string
    {
        switch ((string) ($x['kind'] ?? '')) {
            case 'unjournalised_sales':
                return $this->t(
                    sprintf('The desks invoiced %s across %s invoices this month, but only %s reached the ledger — %s of it is not journalised, so every profit figure understates the business by that much.',
                        $this->m($this->f($x['invoiced'])), $this->num($x['invoices']), $this->m($this->f($x['booked'])), $this->m($this->f($x['gap']))),
                    sprintf('এ মাসে ডেস্কগুলো %s ইনভয়েস করেছে (%s টা ইনভয়েস), কিন্তু খতিয়ানে পৌঁছেছে মাত্র %s — %s এখনো জার্নাল হয়নি, তাই লাভের প্রতিটা হিসাব ঠিক ততটাই কম দেখাচ্ছে।',
                        $this->m($this->f($x['invoiced'])), $this->num($x['invoices']), $this->m($this->f($x['booked'])), $this->m($this->f($x['gap']))));

            case 'payslip_math':
                $w = $x['worst'];
                return $this->t(
                    sprintf('%s of %s payslips do not add up on their own numbers. The worst is %s for %s: gross %s less %s of deductions should net %s, but the slip says %s.',
                        $this->num($x['count']), $this->num($x['total']), (string) $w['name'], Phrase::monthName((string) $w['month'], 'en'),
                        $this->m($this->f($w['gross'])), $this->m($this->f($w['deductions'])), $this->m($this->f($w['expected'])), $this->m($this->f($w['net']))),
                    sprintf('%s টার মধ্যে %s টা পে-স্লিপ নিজের অঙ্কেই মিলছে না। সবচেয়ে খারাপ %s এর %s মাসের স্লিপ: গ্রস %s থেকে %s কর্তন হলে নিট %s হওয়ার কথা, কিন্তু স্লিপে আছে %s।',
                        $this->num($x['total']), $this->num($x['count']), (string) $w['name'], Phrase::monthName((string) $w['month'], 'bn'),
                        $this->m($this->f($w['gross'])), $this->m($this->f($w['deductions'])), $this->m($this->f($w['expected'])), $this->m($this->f($w['net']))));

            case 'trial_balance':
                return $this->t(
                    sprintf('The trial balance is out by %s.', $this->m(abs($this->f($x['amount'])))),
                    sprintf('রেওয়ামিলে %s গরমিল।', $this->m(abs($this->f($x['amount'])))));

            case 'shared_scope':
                return $this->t(
                    'These shared posting accounts carry a company id when they must be NULL: ' . Phrase::join((array) $x['accounts'], 'en') . '.',
                    'এই শেয়ার্ড হিসাবগুলোতে কোম্পানি আইডি বসে আছে, অথচ NULL থাকার কথা: ' . Phrase::join((array) $x['accounts'], 'bn') . '।');

            case 'negative_balance':
                return $this->t(
                    sprintf('%s shows a negative balance of %s, which a real cash account cannot have.', (string) $x['name'], $this->m($this->f($x['amount']))),
                    sprintf('%s এ ঋণাত্মক ব্যালেন্স %s দেখাচ্ছে — সত্যিকারের নগদ হিসাবে এটা সম্ভব না।', (string) $x['name'], $this->m($this->f($x['amount']))));

            case 'duplicate_expense':
                $w = $x['worst'];
                return $this->t(
                    sprintf('%s expenses look entered twice, for example "%s" at %s.', $this->num($x['count']), (string) $w['title'], $this->m($this->f($w['amount']))),
                    sprintf('%s টা খরচ দুইবার বসানো মনে হচ্ছে, যেমন "%s" — %s।', $this->num($x['count']), (string) $w['title'], $this->m($this->f($w['amount']))));

            case 'no_schedules':
                return $this->t(
                    sprintf('%s of invoices are open with no payment schedule behind them, so no report will ever chase that money.', $this->m($this->f($x['amount']))),
                    sprintf('%s ইনভয়েস খোলা আছে অথচ পেছনে কোনো পেমেন্ট সূচি নেই, তাই ওই টাকার পেছনে কোনো রিপোর্টই লাগবে না।', $this->m($this->f($x['amount']))));
        }
        return '';
    }

    private function errorFix(string $key): string
    {
        $map = [
            'journalise_sales' => [
                'Post the missing sales. Each ticket, visa, contract-flight and contract-file invoice needs its journal entry — debit 1311 Customer Receivable, credit the 4xxx income account, direct cost to 5xxx. Ask the accountant to journalise this month before anyone reads a profit figure.',
                'বাকি বিক্রিগুলো জার্নাল করুন। প্রতিটা টিকিট, ভিসা, কন্ট্রাক্ট ফ্লাইট আর কন্ট্রাক্ট ফাইল ইনভয়েসের এন্ট্রি লাগবে — ডেবিট ১৩১১ Customer Receivable, ক্রেডিট ৪xxx আয়ের হিসাব, সরাসরি খরচ ৫xxx-এ। কেউ লাভের হিসাব দেখার আগে হিসাবরক্ষককে এ মাসেরটা জার্নাল করতে বলুন।'],
            'payslip_math' => [
                'Do not pay against these slips until they are recomputed. Check whether an allowance is being added outside gross_salary, and whether total_deductions matches the components it is made of — on several slips it does not. Rerun payroll for the affected months rather than editing the numbers by hand.',
                'এই স্লিপগুলোর বিপরীতে টাকা দেওয়ার আগে আবার হিসাব করান। দেখুন gross_salary-র বাইরে কোনো ভাতা যোগ হচ্ছে কি না, আর total_deductions তার নিজের উপাদানগুলোর সাথে মেলে কি না — কয়েকটায় মিলছে না। হাতে সংখ্যা বদলানোর বদলে ওই মাসগুলোর পে-রোল আবার চালান।'],
            'shared_scope' => [
                'Set company_id back to NULL on the shared posting accounts. Reports filter accounts by company but journal items by entry, so one tagged account silently breaks that company\'s trial balance.',
                'শেয়ার্ড হিসাবগুলোর company_id আবার NULL করে দিন। রিপোর্ট হিসাব ফিল্টার করে কোম্পানি দিয়ে কিন্তু আইটেম ফিল্টার করে এন্ট্রি দিয়ে, তাই একটা ট্যাগ হয়ে যাওয়া হিসাব চুপচাপ ওই কোম্পানির রেওয়ামিল ভেঙে দেয়।'],
            'negative_balance' => [
                'A negative cash account means a payment was posted that the account never held. Find the entry and reverse it — never edit it, the ERP corrects by reversal.',
                'নগদ হিসাব ঋণাত্মক মানে এমন একটা পেমেন্ট বসেছে যা ওই হিসাবে কখনো ছিল না। এন্ট্রিটা খুঁজে উল্টো এন্ট্রি দিন — কখনো এডিট করবেন না, ERP সংশোধন করে উল্টো এন্ট্রি দিয়ে।'],
            'duplicate_expense' => [
                'Reverse the duplicate rather than deleting it, so the audit trail stays whole, and check whether the money actually left twice before you write it off.',
                'ডুপ্লিকেটটা মুছবেন না, উল্টো এন্ট্রি দিন — তাতে অডিট ট্রেইল অক্ষত থাকে; আর বাতিল করার আগে দেখুন টাকাটা সত্যিই দুইবার বেরিয়েছে কি না।'],
            'no_schedules' => [
                'Raise a payment schedule at the point of sale. Receivables live in Payment Schedules in this ERP, so an invoice without one is money nobody is chasing.',
                'বিক্রির সময়েই পেমেন্ট সূচি খুলুন। এই ERP-তে পাওনা থাকে পেমেন্ট সূচিতে, তাই সূচি ছাড়া ইনভয়েস মানে এমন টাকা যার পেছনে কেউ নেই।'],
        ];
        if (!isset($map[$key])) return '';
        return $this->t($map[$key][0], $map[$key][1]);
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

        // "most revenue", "burning money" and "doing well" are the same question
        // about three different columns — rank by the one he actually asked for
        $metric = (string) ($this->c['slots']['metric'] ?? 'profit');
        $key = ['revenue' => 'income', 'cash' => 'cash', 'people' => 'headcount',
                'loss' => 'profit', 'profit' => 'profit'][$metric] ?? 'profit';
        $asc = ($metric === 'loss');
        usort($live, fn($x, $y) => $asc ? ($x[$key] <=> $y[$key]) : ($y[$key] <=> $x[$key]));

        $label = ['income' => ['revenue', 'আয়'], 'cash' => ['cash', 'নগদ'],
                  'headcount' => ['headcount', 'জনবল'], 'profit' => ['profit', 'মুনাফা']][$key];

        // on a profit ranking the sign is the point, so show it either way
        $signed = ($key === 'profit');
        $fmt = fn($c) => $key === 'headcount'
            ? $c['name'] . ' ' . $this->num($c[$key])
            : $c['name'] . ' ' . ($c[$key] >= 0 ? ($signed ? '+' : '') : '−') . $this->m(abs($this->f($c[$key])));

        $lines = array_map($fmt, array_slice($live, 0, 5));
        $top = $live[0];
        $bottom = end($live);

        $verdict = $asc
            ? $this->t(sprintf('%s is burning the most — %s this month.', $top['name'], $this->m(abs($this->f($top['profit'])))),
                       sprintf('সবচেয়ে বেশি পুড়ছে %s — এ মাসে %s।', $top['name'], $this->m(abs($this->f($top['profit'])))))
            : $this->t(sprintf('%s leads on %s; %s is at the bottom.', $top['name'], $label[0], $bottom['name']),
                       sprintf('%s অনুযায়ী এগিয়ে %s; সবার নিচে %s।', $label[1], $top['name'], $bottom['name']));

        // the honest caveat: the ledger is not the whole business this month
        $caveat = '';
        if (method_exists($this->I(), 'ledgerErrors')) {
            foreach ($this->I()->ledgerErrors()['items'] as $x) {
                if (($x['kind'] ?? '') === 'unjournalised_sales') {
                    $caveat = $this->t(
                        sprintf('Treat that ranking carefully: %s of invoiced sales is not journalised yet, so the ledger holds only %s%% of the real trading.',
                            $this->m($this->f($x['gap'])), $this->num($x['pct'])),
                        sprintf('এই ক্রমটা একটু সাবধানে নিন: %s ইনভয়েস করা বিক্রি এখনো জার্নাল হয়নি, তাই খতিয়ানে আসল ব্যবসার মাত্র %s%% আছে।',
                            $this->m($this->f($x['gap'])), $this->num($x['pct'])));
                    break;
                }
            }
        }

        return $this->say([
            $this->open($asc ? 'bad' : 'ok'),
            $this->t(sprintf('For %s, by %s: ', Phrase::monthName((string) $r['month'], 'en'), $label[0]),
                     sprintf('%s মাসে, %s অনুযায়ী: ', Phrase::monthName((string) $r['month'], 'bn'), $label[1])),
            Phrase::join($lines, $this->L()) . $this->t('.', '।'),
            $verdict,
            $caveat,
            $asc
                ? $this->act('the loss-making side is worth an hour before the profitable one is worth a review.',
                             'যেটা লোকসান দিচ্ছে সেটায় এক ঘণ্টা দেওয়া, যেটা লাভ দিচ্ছে সেটা রিভিউ করার চেয়ে বেশি কাজে দেবে।')
                : $this->act('ask me why for any one of them and I will take that company apart.',
                             'এদের যেকোনো একটার কারণ জানতে চাইলে বলুন, ওই কোম্পানিটা খুলে দেখাব।'),
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

    /* ---------------- the travel desk ---------------- */

    /** visa processing in hand: how many, at what stage, costing against sale price */
    private function a_service_ops(): string
    {
        $this->used('get_service_operations');
        $r = $this->c['tools']->run('get_service_operations', []);
        $vp = $r['visa_processes'] ?? [];
        $n = (int) ($vp['count'] ?? 0);
        if ($n === 0) {
            return $this->say([$this->open('ok'),
                $this->t('There is no visa processing on the books at the moment.', 'এই মুহূর্তে খাতায় কোনো ভিসা প্রসেসিং নেই।')]);
        }
        $cost = $this->f($vp['cost_total'] ?? 0); $sale = $this->f($vp['sale_total'] ?? 0);
        $margin = $sale - $cost;
        $stages = implode(', ', array_map(fn($s) => (string) $s['stage'] . ' ' . $this->num($s['count']), array_slice($vp['by_stage'] ?? [], 0, 5)));
        $head = $this->t(
            sprintf('%s visa files are in hand — %s.', $this->num($n), $stages),
            sprintf('হাতে %s টা ভিসা ফাইল আছে — %s।', $this->num($n), $stages));
        $money = $this->t(
            sprintf('They cost %s and sell for %s, so the desk is carrying %s of margin.', $this->m($cost), $this->m($sale), $this->m($margin)),
            sprintf('খরচ %s, বিক্রি %s — অর্থাৎ ডেস্কের হাতে %s মার্জিন।', $this->m($cost), $this->m($sale), $this->m($margin)));
        $owed = $this->f($vp['unpaid_to_vendor'] ?? 0);
        $vendor = $owed > 0
            ? $this->t(sprintf('%s of that cost is still unpaid to the visa vendors.', $this->m($owed)),
                       sprintf('ওই খরচের %s এখনো ভিসা ভেন্ডরদের দেওয়া হয়নি।', $this->m($owed)))
            : '';
        $extra = ($r['other_visa_services']['count'] ?? 0) > 0
            ? $this->t(sprintf('Plus %s other visa services worth %s.', $this->num($r['other_visa_services']['count']), $this->m($this->f($r['other_visa_services']['sale_total'] ?? 0))),
                       sprintf('সঙ্গে আরও %s টা অন্যান্য ভিসা সেবা, মূল্য %s।', $this->num($r['other_visa_services']['count']), $this->m($this->f($r['other_visa_services']['sale_total'] ?? 0))))
            : '';
        return $this->say([$this->open($margin > 0 ? 'ok' : 'warn'), $head, $money, $vendor, $extra,
            $this->act('ask me for ticket sales if you want the other half of the desk.',
                       'ডেস্কের বাকি অর্ধেক দেখতে টিকিট বিক্রির কথা জিজ্ঞাসা করুন।')]);
    }

    /** the ticket side: what was invoiced, what is unpaid, and what we owe the portals */
    private function a_ticket_business(): string
    {
        $this->used('get_sales');
        $this->used('get_service_operations');
        $mk = $this->monthKey();
        $from = $mk . '-01';
        $to = ($mk === substr($this->A()->today(), 0, 7)) ? $this->A()->today() : date('Y-m-t', strtotime($from));
        $sb = $this->A()->salesBooked($from, $to);
        $line = null;
        foreach (($sb['by_line'] ?? []) as $l) if (($l['line'] ?? '') === 'air ticket') $line = $l;
        $ops = $this->c['tools']->run('get_service_operations', []);
        $tp = $ops['ticket_purchases'] ?? [];

        $sold = $line
            ? $this->t(sprintf('Air tickets invoiced %s in %s across %s invoices, of which %s has been collected.',
                    $this->m($this->f($line['invoiced'])), Phrase::monthName($mk, 'en'), $this->num($line['invoices']), $this->m($this->f($line['collected']))),
                sprintf('%s মাসে এয়ার টিকিটে বিল হয়েছে %s, %s টা ইনভয়েসে — তার মধ্যে আদায় হয়েছে %s।',
                    Phrase::monthName($mk, 'bn'), $this->m($this->f($line['invoiced'])), $this->num($line['invoices']), $this->m($this->f($line['collected']))))
            : $this->t(sprintf('No air ticket was invoiced in %s.', Phrase::monthName($mk, 'en')),
                       sprintf('%s মাসে কোনো এয়ার টিকিট বিল হয়নি।', Phrase::monthName($mk, 'bn')));

        $due = $this->f($tp['due'] ?? 0);
        $cost = $this->t(
            sprintf('On the buying side %s of ticket purchases is still owed.', $this->m($due)),
            sprintf('কেনার দিকে টিকিট পারচেজের %s এখনো বাকি।', $this->m($due)));
        $owed = '';
        if (!empty($tp['owed_to'])) {
            $who = implode(', ', array_map(fn($o) => (string) $o['party'] . ' ' . $this->m($this->f($o['due'])), array_slice($tp['owed_to'], 0, 3)));
            $owed = $this->t(sprintf('Owed to: %s.', $who), sprintf('কাকে দিতে হবে: %s।', $who));
        }
        // a booking portal is a credit line, not a supplier invoice — worth saying so
        $portal = '';
        foreach (($ops['portals'] ?? []) as $p) {
            if ($this->f($p['balance'] ?? 0) < 0) {
                $portal = $this->t(sprintf('The %s portal is running a negative balance of %s.', (string) $p['name'], $this->m(abs($this->f($p['balance'])))),
                                   sprintf('%s পোর্টালের ব্যালান্স ঋণাত্মক — %s।', (string) $p['name'], $this->m(abs($this->f($p['balance'])))));
                break;
            }
        }
        return $this->say([$this->open($due > 0 ? 'warn' : 'ok'), $sold, $cost, $owed, $portal,
            $this->act('a portal balance is a credit line — clear it before the next booking window closes.',
                       'পোর্টাল ব্যালান্স একটা ক্রেডিট লাইন — পরের বুকিং উইন্ডো বন্ধ হওয়ার আগে মিটিয়ে দিন।')]);
    }

    /** the travelling clients on file */
    private function a_clients(): string
    {
        $D = $this->A()->dataset();
        $ph = $D['passport_holders'] ?? [];
        if (!$ph) {
            return $this->say([$this->open('ok'),
                $this->t('No passport holders are on file.', 'খাতায় কোনো পাসপোর্ট হোল্ডার নেই।')]);
        }
        $today = $this->A()->today();
        $soon = date('Y-m-d', strtotime('+180 days', strtotime($today)));
        $expiring = array_values(array_filter($ph, function ($h) use ($today, $soon) {
            $e = substr((string) ($h['expiry_date'] ?? ''), 0, 10);
            return $e !== '' && $e >= $today && $e <= $soon;
        }));
        $head = $this->t(
            sprintf('%s passport holders are on file.', $this->num(count($ph))),
            sprintf('খাতায় %s জন পাসপোর্ট হোল্ডার আছেন।', $this->num(count($ph))));
        // an expiring passport is a booking that cannot fly — worth surfacing unprompted
        $exp = $expiring
            ? $this->t(sprintf('%s of them have a passport expiring within six months%s.', $this->num(count($expiring)),
                    count($expiring) ? ' — ' . implode(', ', array_map(fn($h) => (string) $h['name'], array_slice($expiring, 0, 4))) : ''),
                sprintf('তাঁদের %s জনের পাসপোর্ট ছয় মাসের মধ্যে মেয়াদ শেষ হবে%s।', $this->num(count($expiring)),
                    count($expiring) ? ' — ' . implode(', ', array_map(fn($h) => (string) $h['name'], array_slice($expiring, 0, 4))) : ''))
            : $this->t('None of their passports expire within six months.', 'ছয় মাসের মধ্যে কারও পাসপোর্টের মেয়াদ শেষ হচ্ছে না।');
        return $this->say([$this->open($expiring ? 'warn' : 'ok'), $head, $exp,
            $expiring ? $this->act('most embassies refuse a passport with under six months left — tell the desk before they book.',
                                   'বেশির ভাগ দূতাবাস ছয় মাসের কম মেয়াদের পাসপোর্ট নেয় না — বুকিংয়ের আগে ডেস্ককে জানিয়ে দিন।') : '']);
    }

    /* ---------------- people, ranked ---------------- */

    private function a_staff_ranking(): string
    {
        $this->used('evaluate_all_staff');
        $r = $this->A()->ranking(30, 5);
        if (!$r['rated']) {
            return $this->say([$this->open('warn'),
                $this->t('I cannot rank anyone yet — nobody has enough attendance or task history in the last 30 days.',
                         'এখনো কাউকে র‍্যাঙ্ক করতে পারছি না — গত ৩০ দিনে যথেষ্ট হাজিরা বা কাজের রেকর্ড নেই।')]);
        }
        $wantWorst = str_contains($this->c['norm'] ?? '', 'worst') || str_contains($this->c['norm'] ?? '', 'খারাপ')
            || str_contains($this->c['norm'] ?? '', 'underperform') || str_contains($this->c['norm'] ?? '', 'not doing well');
        $list = $wantWorst ? $r['bottom'] : $r['top'];
        $names = implode(', ', array_map(fn($p) => (string) $p['name'] . ' ' . $this->num($p['score']) . '/' . $this->num(100) . ' (' . $p['grade'] . ')', array_slice($list, 0, 5)));
        $head = $wantWorst
            ? $this->t(sprintf('Lowest scoring over the last 30 days: %s.', $names), sprintf('গত ৩০ দিনে সবচেয়ে কম স্কোর: %s।', $names))
            : $this->t(sprintf('Top of the last 30 days: %s.', $names), sprintf('গত ৩০ দিনের সেরা: %s।', $names));
        $base = $this->t(
            sprintf('That is %s people with enough history to judge, averaging %s/100; %s have too little record to rate.',
                $this->num($r['rated']), $this->num($r['average']), $this->num($r['unrated'])),
            sprintf('বিচার করার মতো রেকর্ড আছে %s জনের, গড় %s/১০০; %s জনের রেকর্ড কম বলে র‍্যাঙ্ক করা যায়নি।',
                $this->num($r['rated']), $this->num($r['average']), $this->num($r['unrated'])));
        $dept = !empty($r['by_department'])
            ? $this->t(sprintf('Best department: %s at %s/100.', (string) $r['by_department'][0]['department'], $this->num($r['by_department'][0]['average'])),
                       sprintf('সেরা বিভাগ: %s, %s/১০০।', (string) $r['by_department'][0]['department'], $this->num($r['by_department'][0]['average'])))
            : '';
        $worry = !empty($r['concerns'])
            ? $this->t(sprintf('%s need attention: %s.', $this->num(count($r['concerns'])), implode(', ', array_map(fn($p) => (string) $p['name'], array_slice($r['concerns'], 0, 4)))),
                       sprintf('%s জনের দিকে নজর দিতে হবে: %s।', $this->num(count($r['concerns'])), implode(', ', array_map(fn($p) => (string) $p['name'], array_slice($r['concerns'], 0, 4)))))
            : '';
        return $this->say([$this->open($wantWorst ? 'warn' : 'good'), $head, $base, $dept, $worry,
            $this->act('ask me about any of them by name for the full picture.', 'যে কারও নাম ধরে জিজ্ঞাসা করলে পুরো চিত্র দেব।')]);
    }

    /* ---------------- utilities ---------------- */

    /** set or read back how EON should behave — the browser keeps the same shape in localStorage */
    private function a_preferences(): string
    {
        $q = trim((string) ($this->c['q'] ?? ''));
        $set = null;   // [kind, value]
        if (preg_match('/(?:call me|address me as|my name is|আমাকে ডাকবে|আমাকে ডাকো|আমার নাম)\s+(.{1,40})$/ui', $q, $m)) $set = ['name', (string) preg_replace("/^[\\s\"'.।?!]+|[\\s\"'.।?!]+$/u", '', $m[1])];
        elseif (preg_match('/\b(?:in |দেখাও )?(lakh|crore|full|লক্ষ|কোটি)\b/ui', $q, $m) && preg_match('/(?:show money|money in|টাকা দেখাও|লক্ষে|কোটিতে)/ui', $q)) {
            $u = mb_strtolower($m[1]); $set = ['money_unit', $u === 'লক্ষ' ? 'lakh' : ($u === 'কোটি' ? 'crore' : $u)];
        } elseif (preg_match('/(?:be brief|short answers|সংক্ষেপে বলো)/ui', $q)) $set = ['brevity', 'short'];
        elseif (preg_match('/(?:speak|answer in)\s+bangla|বাংলায় বলো|bangla te bolo/ui', $q)) $set = ['language', 'bn'];
        elseif (preg_match('/(?:speak|answer in)\s+english|ইংরেজিতে বলো/ui', $q)) $set = ['language', 'en'];
        elseif (preg_match('/brief me at\s+(\d{1,2})/ui', $q, $m)) $set = ['brief_hour', $m[1]];
        elseif (preg_match('/(?:remember that|মনে রাখো)\s+(.{2,300})$/ui', $q, $m)) $set = ['note', trim($m[1])];
        elseif (preg_match('/(?:forget|ভুলে যাও)\s+(.{2,300})$/ui', $q, $m)) $set = ['forget', trim($m[1])];

        if ($set !== null) {
            $this->used('remember_preference');
            $res = $this->c['tools']->run('remember_preference', ['kind' => $set[0], 'value' => (string) $set[1]]);
            if (is_array($res) && !empty($res['error'])) {
                return $this->say([$this->open('warn'),
                    $this->t('I could not save that — the memory store is not available on this host.',
                             'এটা রাখতে পারিনি — এই সার্ভারে মেমোরি স্টোর চালু নেই।')]);
            }
            // the tool confirms in English; compose the Bangla confirmation here
            if ($this->bn()) {
                $v = (string) $set[1];
                $bnUnit = ['lakh' => 'লক্ষ', 'crore' => 'কোটি', 'full' => 'পুরো অঙ্কে', 'auto' => 'নিজে থেকে'];
                $msg = match ($set[0]) {
                    'name' => 'ঠিক আছে — এখন থেকে আপনাকে ' . $v . ' বলে ডাকব।',
                    'money_unit' => 'টাকা এখন থেকে ' . ($bnUnit[$v] ?? $v) . ' হিসেবে দেখাব।',
                    'brevity' => 'এখন থেকে সংক্ষেপে বলব।',
                    'language' => $v === 'bn' ? 'এখন থেকে বাংলাতেই বলব।' : 'এখন থেকে ইংরেজিতে বলব।',
                    'brief_hour' => 'ব্রিফ দেব ' . $this->num($v) . 'টায়।',
                    'note' => 'মনে রাখলাম।',
                    'forget' => 'ভুলে গেলাম।',
                    default => 'মনে রাখলাম।',
                };
                return $this->say([$this->open('good'), $msg]);
            }
            $spoken = is_array($res) ? (string) ($res['speak'] ?? $res['confirmation'] ?? '') : '';
            if ($spoken !== '') return $this->say([$this->open('good'), $spoken]);
            return $this->say([$this->open('good'), 'Noted — I will remember that.']);
        }

        // no instruction found → read the preferences back
        $this->used('get_preferences');
        $p = $this->c['tools']->run('get_preferences', []);
        $prefs = is_array($p) ? ($p['prefs'] ?? $p) : [];
        $bits = [];
        if (!empty($prefs['name'])) $bits[] = $this->t('I call you ' . $prefs['name'], 'আপনাকে ডাকি ' . $prefs['name']);
        $unit = (string) ($prefs['money_unit'] ?? 'auto');
        $bnUnit = ['lakh' => 'লক্ষে', 'crore' => 'কোটিতে', 'full' => 'পুরো অঙ্কে', 'auto' => 'নিজে থেকে'][$unit] ?? $unit;
        $brev = (string) ($prefs['brevity'] ?? 'normal');
        $bits[] = $this->t('money in ' . $unit, 'টাকা দেখাই ' . $bnUnit);
        $bits[] = $this->t('answers in ' . (($prefs['language'] ?? 'en') === 'bn' ? 'Bangla' : 'English'), 'উত্তর দিই ' . (($prefs['language'] ?? 'en') === 'bn' ? 'বাংলায়' : 'ইংরেজিতে'));
        $bits[] = $this->t($brev . ' length', $brev === 'short' ? 'সংক্ষেপে' : 'স্বাভাবিক দৈর্ঘ্যে');
        $bits[] = $this->t('brief at ' . $this->num($prefs['brief_hour'] ?? 8) . ':00', 'ব্রিফ ' . $this->num($prefs['brief_hour'] ?? 8) . 'টায়');
        $notes = (array) ($prefs['notes'] ?? []);
        return $this->say([$this->open('ok'),
            $this->t('What I remember: ' . implode(', ', $bits) . '.', 'যা মনে রেখেছি: ' . implode(', ', $bits) . '।'),
            $notes ? $this->t($this->num(count($notes)) . ' note(s) kept: ' . implode('; ', array_map(fn($n) => (string) (is_array($n) ? $n['text'] : $n), array_slice($notes, 0, 3))) . '.',
                              $this->num(count($notes)) . 'টি নোট রাখা আছে: ' . implode('; ', array_map(fn($n) => (string) (is_array($n) ? $n['text'] : $n), array_slice($notes, 0, 3))) . '।') : '',
            $this->act('say "call me …", "show money in crore", "be brief" or "remember that …" to change it.',
                       '"আমাকে ডাকো …", "কোটিতে দেখাও", "সংক্ষেপে বলো" বা "মনে রাখো …" বললেই বদলে যাবে।')]);
    }

    /* One named person, any aspect. This is the handler that makes the space large:
       "Imran's payroll", "payroll of Imran", "ইমরানের বেতন", "Imran er hajira",
       "take me to Imran's payslip" all arrive here as (person, aspect) and are
       answered from a single dossier rather than an intent per phrasing. */
    private function a_person_aspect(): string
    {
        $s = $this->c['slots'];
        $uid = (int) ($s['instance_id'] ?? 0);
        $label = (string) ($s['instance_label'] ?? '');
        if ($uid <= 0) {
            $e = $this->A()->findEmployee((string) ($s['name_hint'] ?? $this->c['q'] ?? ''));
            if ($e) { $uid = (int) $e['id']; $label = (string) $e['name']; }
        }
        if ($uid <= 0) return $this->a_evaluate_person();
        $this->used('find_employee');
        $p = $this->A()->person($uid);
        if (!$p) return $this->a_evaluate_person();
        $e = $p['employee'];
        $who = (string) $e['name'];
        $aspect = (string) ($s['aspect'] ?? '');

        // no aspect named → the profile card, which is what "who is X" means
        switch ($aspect) {
            case 'payroll':
                $this->used('get_payroll');
                $latest = $p['payroll']['latest'] ?? null;
                $unpaid = $p['payroll']['unpaid'] ?? [];
                $head = $this->t(
                    sprintf('%s is on %s a month.', $who, $this->m($this->f($e['salary']))),
                    sprintf('%s-এর মাসিক বেতন %s।', $who, $this->m($this->f($e['salary']))));
                $last = $latest
                    ? $this->t(sprintf('The last payslip on record is %s: gross %s, deductions %s, net %s — %s.',
                            (string) ($latest['month'] ?? $latest['month_key'] ?? ''), $this->m($this->f($latest['gross_salary'])), $this->m($this->f($latest['total_deductions'])), $this->m($this->f($latest['net_salary'])), (string) ($latest['status'] ?? '')),
                        sprintf('সর্বশেষ পে-স্লিপ %s: মোট %s, কর্তন %s, নিট %s — %s।',
                            (string) ($latest['month'] ?? $latest['month_key'] ?? ''), $this->m($this->f($latest['gross_salary'])), $this->m($this->f($latest['total_deductions'])), $this->m($this->f($latest['net_salary'])), (string) ($latest['status'] ?? '')))
                    : $this->t('No payslip has been generated for them yet.', 'তাঁর কোনো পে-স্লিপ এখনো তৈরি হয়নি।');
                $due = $this->f($p['payroll']['scheduled_due']);
                $owed = $due > 0
                    ? $this->t(sprintf('%s is scheduled and still unpaid.', $this->m($due)), sprintf('%s এখনো বাকি আছে।', $this->m($due)))
                    : ($unpaid ? $this->t($this->num(count($unpaid)) . ' payslip(s) are unpaid.', $this->num(count($unpaid)) . 'টি পে-স্লিপ অপরিশোধিত।') : '');
                return $this->say([$this->open($due > 0 ? 'warn' : 'ok'), $head, $last, $owed]);

            case 'payslip':
                $docs = $p['payslip_docs'] ?? [];
                $slips = $p['payroll']['payslips'] ?? 0;
                return $this->say([$this->open('ok'),
                    $this->t(sprintf('%s has %s payslip(s) on record%s.', $who, $this->num($slips), $docs ? ' and ' . $this->num(count($docs)) . ' issued document(s)' : ''),
                        sprintf('%s-এর %s টি পে-স্লিপ আছে%s।', $who, $this->num($slips), $docs ? ', এর মধ্যে ' . $this->num(count($docs)) . ' টি ইস্যু করা' : '')),
                    $this->screenLine('payslip')]);

            case 'attendance':
                $a = $p['attendance'];
                $today = $a['today'] ?? null;
                return $this->say([$this->open(($a['pct'] ?? 100) >= 85 ? 'ok' : 'warn'),
                    $this->t(sprintf('Over the last %s days %s was present on %s of %s recorded days%s.', $this->num($p['days']), $who, $this->num($a['present']), $this->num($a['recorded']), $a['pct'] !== null ? ' (' . $this->pc((float) $a['pct']) . ')' : ''),
                        sprintf('গত %s দিনে %s %s দিনের মধ্যে %s দিন উপস্থিত ছিলেন%s।', $this->num($p['days']), $who, $this->num($a['recorded']), $this->num($a['present']), $a['pct'] !== null ? ' (' . $this->pc((float) $a['pct']) . ')' : '')),
                    $today ? $this->t(sprintf('Today: %s, in at %s.', (string) $today['status'], (string) ($today['check_in'] ?? '—')),
                                      sprintf('আজ: %s, ঢুকেছেন %s-এ।', (string) $today['status'], (string) ($today['check_in'] ?? '—')))
                           : $this->t('Nothing recorded for today yet.', 'আজকের কিছু এখনো ওঠেনি।'),
                    $a['absent'] ? $this->t($this->num($a['absent']) . ' absent, ' . $this->num($a['on_leave']) . ' on leave.', $this->num($a['absent']) . ' দিন অনুপস্থিত, ' . $this->num($a['on_leave']) . ' দিন ছুটিতে।') : '']);

            case 'lateness':
                $l = $p['lateness'];
                return $this->say([$this->open($l['days'] > 3 ? 'warn' : 'ok'),
                    $l['days']
                        ? $this->t(sprintf('%s was late on %s day(s) in the last %s, %s minutes in total (worst %s).', $who, $this->num($l['days']), $this->num($p['days']), $this->num($l['minutes']), $this->num($l['worst'])),
                            sprintf('%s গত %s দিনে %s দিন দেরি করেছেন, মোট %s মিনিট (সবচেয়ে বেশি %s)।', $who, $this->num($p['days']), $this->num($l['days']), $this->num($l['minutes']), $this->num($l['worst'])))
                        : $this->t(sprintf('%s has not been late in the last %s days.', $who, $this->num($p['days'])), sprintf('%s গত %s দিনে দেরি করেননি।', $who, $this->num($p['days']))),
                    $l['minutes'] >= 120
                        ? $this->t('That passes the 120-minute monthly grace, so the late deduction applies.', 'এটা মাসিক ১২০ মিনিটের ছাড় পেরিয়েছে, তাই দেরির কর্তন প্রযোজ্য।')
                        : ($l['minutes'] ? $this->t(sprintf('Still inside the 120-minute monthly grace — no deduction yet.'), 'এখনো ১২০ মিনিটের ছাড়ের ভেতরে — কর্তন হবে না।') : '')]);

            case 'leave':
                $lv = $p['leaves'];
                $pending = array_values(array_filter($lv, fn($x) => strtolower((string) ($x['status'] ?? '')) === 'pending'));
                $daysTaken = array_sum(array_map(fn($x) => (int) ($x['days'] ?? 0), array_filter($lv, fn($x) => strtolower((string) ($x['status'] ?? '')) === 'approved')));
                return $this->say([$this->open($pending ? 'warn' : 'ok'),
                    $this->t(sprintf('%s has %s leave application(s) on record, %s day(s) approved this year.', $who, $this->num(count($lv)), $this->num($daysTaken)),
                        sprintf('%s-এর %s টি ছুটির আবেদন আছে, এ বছরে অনুমোদিত %s দিন।', $who, $this->num(count($lv)), $this->num($daysTaken))),
                    $pending ? $this->t($this->num(count($pending)) . ' still waiting on approval.', $this->num(count($pending)) . 'টি এখনো অনুমোদনের অপেক্ষায়।') : '']);

            case 'loan':
                return $this->say([$this->open($p['loan_remaining'] > 0 ? 'warn' : 'ok'),
                    $p['loans']
                        ? $this->t(sprintf('%s has %s running loan(s) with %s still to recover.', $who, $this->num(count($p['loans'])), $this->m($this->f($p['loan_remaining']))),
                            sprintf('%s-এর %s টি চলমান ঋণ আছে, আদায় বাকি %s।', $who, $this->num(count($p['loans'])), $this->m($this->f($p['loan_remaining']))))
                        : $this->t(sprintf('%s has no outstanding loan.', $who), sprintf('%s-এর কোনো ঋণ বাকি নেই।', $who))]);

            case 'advance':
                $adv = $p['advances'];
                $amt = array_sum(array_map(fn($x) => (float) ($x['amount'] ?? 0), $adv));
                return $this->say([$this->open('ok'),
                    $adv
                        ? $this->t(sprintf('%s has taken %s salary advance(s) totalling %s.', $who, $this->num(count($adv)), $this->m($amt)),
                            sprintf('%s %s বার অগ্রিম বেতন নিয়েছেন, মোট %s।', $who, $this->num(count($adv)), $this->m($amt)))
                        : $this->t(sprintf('%s has taken no salary advance.', $who), sprintf('%s কোনো অগ্রিম বেতন নেননি।', $who))]);

            case 'task':
                $t = $p['tasks'];
                return $this->say([$this->open($t['overdue'] ? 'warn' : 'ok'),
                    $this->t(sprintf('%s has %s open task(s), %s overdue, %s done.', $who, $this->num($t['open']), $this->num($t['overdue']), $this->num($t['done'])),
                        sprintf('%s-এর %s টি কাজ চলছে, %s টির সময় পার, %s টি শেষ।', $who, $this->num($t['open']), $this->num($t['overdue']), $this->num($t['done']))),
                    !empty($t['list']) ? $this->t('Oldest: ' . implode(', ', array_map(fn($x) => (string) $x['title'], array_slice($t['list'], 0, 3))) . '.',
                                                  'সবচেয়ে পুরনো: ' . implode(', ', array_map(fn($x) => (string) $x['title'], array_slice($t['list'], 0, 3))) . '।') : '']);

            case 'project':
                $pj = $p['projects'];
                return $this->say([$this->open('ok'),
                    $pj
                        ? $this->t(sprintf('%s is on %s project(s): %s.', $who, $this->num(count($pj)), implode(', ', array_map(fn($x) => (string) $x['project_name'], array_slice($pj, 0, 4)))),
                            sprintf('%s %s টি প্রকল্পে আছেন: %s।', $who, $this->num(count($pj)), implode(', ', array_map(fn($x) => (string) $x['project_name'], array_slice($pj, 0, 4)))))
                        : $this->t(sprintf('%s is not on any project team.', $who), sprintf('%s কোনো প্রকল্প দলে নেই।', $who))]);

            case 'request':
                $rq = $p['requests'];
                return $this->say([$this->open($rq ? 'warn' : 'ok'),
                    $rq
                        ? $this->t(sprintf('%s has %s request(s) on file; latest is %s.', $who, $this->num(count($rq)), (string) ($rq[0]['status'] ?? '—')),
                            sprintf('%s-এর %s টি আবেদন আছে; সর্বশেষটির অবস্থা %s।', $who, $this->num(count($rq)), (string) ($rq[0]['status'] ?? '—')))
                        : $this->t(sprintf('%s has no open request.', $who), sprintf('%s-এর কোনো আবেদন নেই।', $who))]);

            case 'ledger':
                $lg = $p['ledger'];
                // clients are users too, so a name can be both staff and a trading party;
                // if the employee ledger is empty, the party account is what was meant
                if (!$lg) {
                    $pl = $this->A()->partyLedger($who, 6);
                    if (!empty($pl['parties'])) { $this->c['slots']['name_hint'] = $who; return $this->a_party_balance(); }
                }
                $bal = $lg ? (float) ($lg[0]['balance'] ?? 0) : 0.0;
                return $this->say([$this->open('ok'),
                    $lg
                        ? $this->t(sprintf('%s has %s ledger entries, balance %s.', $who, $this->num(count($lg)), $this->m($bal)),
                            sprintf('%s-এর খতিয়ানে %s টি এন্ট্রি, ব্যালান্স %s।', $who, $this->num(count($lg)), $this->m($bal)))
                        : $this->t(sprintf('Nothing in the employee ledger for %s.', $who), sprintf('%s-এর জন্য কর্মী খতিয়ানে কিছু নেই।', $who))]);

            case 'contact':
                return $this->say([$this->open('ok'),
                    $this->t(sprintf('%s — %s%s.', $who, (string) ($e['phone'] ?: 'no phone on file'), $e['email'] ? ', ' . $e['email'] : ''),
                        sprintf('%s — %s%s।', $who, (string) ($e['phone'] ?: 'ফোন নম্বর নেই'), $e['email'] ? ', ' . $e['email'] : ''))]);

            case 'resignation':
                $r = $p['resignation'];
                return $this->say([$this->open($r ? 'warn' : 'ok'),
                    $r
                        ? $this->t(sprintf('%s resigned on %s, last working day %s — %s.', $who, (string) $r['resign_date'], (string) $r['last_working_day'], (string) $r['status']),
                            sprintf('%s %s তারিখে পদত্যাগ করেছেন, শেষ কর্মদিবস %s — %s।', $who, (string) $r['resign_date'], (string) $r['last_working_day'], (string) $r['status']))
                        : $this->t(sprintf('%s has not resigned.', $who), sprintf('%s পদত্যাগ করেননি।', $who))]);

            case 'department':
                return $this->say([$this->open('ok'),
                    $this->t(sprintf('%s is %s in %s at %s.', $who, (string) ($e['designation'] ?: 'unlisted'), (string) ($e['department'] ?: '—'), (string) $e['company']),
                        sprintf('%s %s পদে আছেন, %s বিভাগে, %s-এ।', $who, (string) ($e['designation'] ?: '—'), (string) ($e['department'] ?: '—'), (string) $e['company']))]);

            case 'evaluation':
                return $this->a_evaluate_person();
        }

        // profile, or an aspect that has no special answer
        $a = $p['attendance'];
        return $this->say([$this->open('ok'),
            $this->t(sprintf('%s — %s, %s, %s. On %s a month since %s.', $who, (string) ($e['designation'] ?: 'unlisted'), (string) ($e['department'] ?: '—'), (string) $e['company'], $this->m($this->f($e['salary'])), (string) ($e['joined'] ?: '—')),
                sprintf('%s — %s, %s, %s। মাসিক বেতন %s, যোগ দিয়েছেন %s-এ।', $who, (string) ($e['designation'] ?: '—'), (string) ($e['department'] ?: '—'), (string) $e['company'], $this->m($this->f($e['salary'])), (string) ($e['joined'] ?: '—'))),
            $this->t(sprintf('Attendance %s over %s days, %s late day(s), %s open task(s).', $a['pct'] !== null ? $this->pc((float) $a['pct']) : 'not tracked', $this->num($p['days']), $this->num($p['lateness']['days']), $this->num($p['tasks']['open'])),
                sprintf('গত %s দিনে হাজিরা %s, দেরি %s দিন, চলমান কাজ %s টি।', $this->num($p['days']), $a['pct'] !== null ? $this->pc((float) $a['pct']) : 'হিসাব নেই', $this->num($p['lateness']['days']), $this->num($p['tasks']['open']))),
            $this->act('ask me for their payroll, attendance, tasks or evaluation and I will go deeper.',
                       'তাঁর বেতন, হাজিরা, কাজ বা মূল্যায়ন চাইলে আরও বিস্তারিত দেব।')]);
    }

    /* A named record that is not a person: a passenger, a project, a company, an
       account, an invoice. The boss names the thing and expects to hear about that
       thing — routing him to the list screen reads as EON not having heard him. */
    private function a_record_aspect(): string
    {
        $s = $this->c['slots'];
        $kind = (string) ($s['instance_kind'] ?? '');
        $id = $s['instance_id'] ?? null;
        $label = (string) ($s['instance_label'] ?? '');
        $D = $this->c['D'];
        $find = fn(string $t, string $key, $needle) => (function () use ($D, $t, $key, $needle) {
            foreach ($D[$t] ?? [] as $r) if ((string) ($r[$key] ?? '') === (string) $needle) return $r;
            return null;
        })();

        switch ($kind) {
            case 'passenger':
                $h = $find('passport_holders', 'id', $id);
                if (!$h) break;
                $name = (string) $h['name'];
                $visas = array_values(array_filter($D['visa_processes'] ?? [], fn($v) => (int) ($v['passport_holder_id'] ?? 0) === (int) $id));
                $exp = substr((string) ($h['expiry_date'] ?? ''), 0, 10);
                $daysLeft = $exp !== '' ? (int) round((strtotime($exp) - strtotime($this->A()->today())) / 86400) : null;
                return $this->say([$this->open($daysLeft !== null && $daysLeft < 180 ? 'warn' : 'ok'),
                    $this->t(sprintf('%s — passport %s, %s.', $name, (string) ($h['passport_no'] ?: '—'), (string) ($h['nationality'] ?: '—')),
                             sprintf('%s — পাসপোর্ট %s, %s।', $name, (string) ($h['passport_no'] ?: '—'), (string) ($h['nationality'] ?: '—'))),
                    $exp !== '' ? $this->t(sprintf('Expires %s (%s days).', $exp, $this->num((int) $daysLeft)), sprintf('মেয়াদ শেষ %s (%s দিন)।', $this->d($exp), $this->num((int) $daysLeft))) : '',
                    $visas ? $this->t(sprintf('%s visa file(s): %s.', $this->num(count($visas)), implode(', ', array_map(fn($v) => (string) ($v['country'] ?? '') . ' ' . (string) ($v['status'] ?? ''), array_slice($visas, 0, 3)))),
                                      sprintf('%s টি ভিসা ফাইল: %s।', $this->num(count($visas)), implode(', ', array_map(fn($v) => (string) ($v['country'] ?? '') . ' ' . (string) ($v['status'] ?? ''), array_slice($visas, 0, 3)))))
                           : $this->t('No visa file on record.', 'কোনো ভিসা ফাইল নেই।'),
                    $daysLeft !== null && $daysLeft < 180 ? $this->act('under six months left — most embassies will refuse it.', 'ছয় মাসের কম মেয়াদ — বেশির ভাগ দূতাবাস নেবে না।') : '']);

            case 'project':
                $p = $find('projects', 'id', $id);
                if (!$p) break;
                $name = (string) $p['project_name'];
                $tasks = array_values(array_filter($D['tasks'] ?? [], fn($t) => (int) ($t['project_id'] ?? 0) === (int) $id));
                $open = array_values(array_filter($tasks, fn($t) => ($t['status'] ?? '') !== 'done'));
                return $this->say([$this->open(($p['progress'] ?? 0) < 50 ? 'warn' : 'ok'),
                    $this->t(sprintf('%s — %s, %s%% done, %s.', $name, (string) ($p['status'] ?? '—'), $this->num($p['progress'] ?? 0), (string) ($p['customer'] ?: 'no customer')),
                             sprintf('%s — %s, %s%% শেষ, %s।', $name, (string) ($p['status'] ?? '—'), $this->num($p['progress'] ?? 0), (string) ($p['customer'] ?: 'গ্রাহক নেই'))),
                    $this->t(sprintf('Budget %s, %s of %s tasks still open, due %s.', $this->m($this->f($p['budget'] ?? 0)), $this->num(count($open)), $this->num(count($tasks)), (string) ($p['end_date'] ?: '—')),
                             sprintf('বাজেট %s, %s টির মধ্যে %s টি কাজ বাকি, শেষ তারিখ %s।', $this->m($this->f($p['budget'] ?? 0)), $this->num(count($tasks)), $this->num(count($open)), $this->d((string) ($p['end_date'] ?: '—'))))]);

            case 'company':
                $c = $find('companies', 'id', $id);
                if (!$c) break;
                $name = (string) $c['name'];
                $A = new Analytics($D, (int) $id);
                $k = $A->kpis();
                return $this->say([$this->open('ok'),
                    $this->t(sprintf('%s — cash %s, receivable %s, payable %s.', $name, $this->m($this->f($k['cash'])), $this->m($this->f($k['receivables'])), $this->m($this->f($k['payables']))),
                             sprintf('%s — হাতে %s, পাওনা %s, দেনা %s।', $name, $this->m($this->f($k['cash'])), $this->m($this->f($k['receivables'])), $this->m($this->f($k['payables'])))),
                    $this->t(sprintf('%s staff, revenue %s this month, net %s.', $this->num($k['headcount']), $this->m($this->f($k['revenue_mtd'])), $this->m($this->f($k['net_profit_mtd']))),
                             sprintf('কর্মী %s জন, এ মাসের আয় %s, নিট %s।', $this->num($k['headcount']), $this->m($this->f($k['revenue_mtd'])), $this->m($this->f($k['net_profit_mtd']))))]);

            case 'account':
                $code = (string) $id;
                $aname = $label;
                foreach ($D['accounts'] ?? [] as $a) {
                    if ((string) ($a['code'] ?? '') === $code || trim((string) ($a['name'] ?? '')) === trim($label)) { $code = (string) $a['code']; $aname = (string) $a['name']; break; }
                }
                $this->c['slots']['account_code'] = $code;
                $body = $this->a_account_ledger();
                // name the account, not just its code — the boss asked for it by name
                $head = $this->t(sprintf('%s is account %s.', $aname, $code), sprintf('%s হলো %s নম্বর হিসাব।', $aname, $this->d($code)));
                return $body === '' ? $head : $head . ' ' . $body;

            case 'invoice':
                $this->c['slots']['name_hint'] = $label;
                return $this->a_find_record();
        }
        // the record vanished between resolving and answering — say so rather than guess
        $this->c['slots']['name_hint'] = $label;
        return $this->a_find_record();
    }

    /** where a screen lives, when the answer is really "take me there" */
    private function screenLine(string $what): string
    {
        if (!class_exists('ErpMap') || !ErpMap::available()) return '';
        $hits = ErpMap::findPages($what, 1);
        if (!$hits) return '';
        $uri = (string) ($hits[0]['uri'] ?? '');
        if ($uri === '') return '';
        return $this->t('The screen is at ' . $uri . '.', 'স্ক্রিনটি আছে ' . $uri . '-এ।');
    }

    private function a_find_record(): string
    {
        $q = trim((string) ($this->c['slots']['name_hint'] ?? ''));
        if ($q === '') {
            // fall back to the words after the search verb
            if (preg_match('/(?:find|search for|look ?up|anything on|খুঁজে দাও|খুঁজুন|সার্চ করো)\s+(.{2,60})/ui', (string) ($this->c['q'] ?? ''), $m)) $q = trim($m[1]);
        }
        if ($q === '') {
            return $this->say([$this->open('ok'),
                $this->t('What should I search for? A name, a reference or an invoice number all work.',
                         'কী খুঁজব? নাম, রেফারেন্স বা ইনভয়েস নম্বর — যেকোনোটা বলুন।')]);
        }
        $this->used('search_records');
        $res = $this->c['tools']->run('search_records', ['query' => $q, 'limit' => 20]);
        // search_records returns a flat list; each row carries the table it came from
        $groups = [];
        foreach ((array) $res as $row) {
            if (!is_array($row)) continue;
            $t = (string) ($row['table'] ?? 'record');
            $label = (string) ($row['name'] ?? $row['title'] ?? $row['party_name'] ?? $row['client'] ?? $row['project_name']
                ?? $row['applicant'] ?? $row['invoice'] ?? $row['ticket_no'] ?? $row['vendor'] ?? $row['source_label'] ?? ('#' . ($row['id'] ?? '?')));
            if ($label === '') continue;
            $groups[$t][$label] = true;
        }
        $names = $this->bn()
            ? ['employees' => 'কর্মী', 'customers' => 'গ্রাহক', 'suppliers' => 'সরবরাহকারী', 'leads' => 'লিড',
               'projects' => 'প্রকল্প', 'tasks' => 'কাজ', 'expenses' => 'খরচ', 'payment_schedules' => 'পেমেন্ট সূচি',
               'ticket_sales' => 'টিকিট বিল', 'visa_sales' => 'ভিসা বিল', 'contract_file_sales' => 'কন্ট্রাক্ট ফাইল বিল',
               'ticket_purchases' => 'টিকিট কেনা', 'visa_processes' => 'ভিসা ফাইল', 'passport_holders' => 'পাসপোর্ট হোল্ডার',
               'party_transactions' => 'খাতার এন্ট্রি', 'support_tickets' => 'সাপোর্ট টিকিট']
            : ['employees' => 'staff', 'customers' => 'customers', 'suppliers' => 'suppliers', 'leads' => 'leads',
               'projects' => 'projects', 'tasks' => 'tasks', 'expenses' => 'expenses', 'payment_schedules' => 'payment schedules',
               'ticket_sales' => 'ticket invoices', 'visa_sales' => 'visa invoices', 'contract_file_sales' => 'contract file invoices',
               'ticket_purchases' => 'ticket purchases', 'visa_processes' => 'visa files', 'passport_holders' => 'passport holders',
               'party_transactions' => 'ledger entries', 'support_tickets' => 'support tickets'];
        $hits = [];
        foreach ($groups as $t => $set) {
            $labels = array_slice(array_keys($set), 0, 3);
            $more = count($set) > 3 ? ' +' . $this->num(count($set) - 3) : '';
            $hits[] = ($names[$t] ?? $t) . ' — ' . implode(', ', $labels) . $more;
        }
        if (!$hits) {
            return $this->say([$this->open('warn'),
                $this->t(sprintf('Nothing matches "%s" in employees, customers, suppliers, leads, projects, tasks, expenses or schedules.', $q),
                         sprintf('"%s" নামে কর্মী, গ্রাহক, সরবরাহকারী, লিড, প্রকল্প, কাজ, খরচ বা সূচিতে কিছু পাইনি।', $q))]);
        }
        return $this->say([$this->open('ok'),
            $this->t(sprintf('Found "%s" in %s.', $q, implode('; ', $hits)), sprintf('"%s" পাওয়া গেছে — %s।', $q, implode('; ', $hits))),
            $this->act('name the one you want and I will open it up.', 'কোনটা দেখতে চান বলুন, খুলে দিচ্ছি।')]);
    }

    /** EON is advisory: it records the instruction, it does not change the ERP */
    private function a_remind(): string
    {
        $raw = trim((string) ($this->c['q'] ?? ''));
        $this->used('record_action');
        $who = (string) ($this->c['slots']['name_hint'] ?? '');
        $res = $this->c['tools']->run('record_action', [
            'kind' => 'remind',
            'summary' => $raw !== '' ? mb_substr($raw, 0, 300) : 'reminder',
            'payload' => array_filter(['party' => $who ?: null, 'asked_at' => $this->A()->today()]),
        ]);
        $ok = is_array($res) && empty($res['error']);
        if (!$ok) {
            return $this->say([$this->open('warn'),
                $this->t('I could not write that down — the memory store is not available on this host.',
                         'এটা লিখে রাখতে পারিনি — এই সার্ভারে মেমোরি স্টোর চালু নেই।')]);
        }
        $head = $who !== ''
            ? $this->t(sprintf('Noted — a reminder about %s.', $who), sprintf('লিখে রাখলাম — %s সংক্রান্ত একটা রিমাইন্ডার।', $who))
            : $this->t('Noted, I have written that down.', 'লিখে রাখলাম।');
        return $this->say([$this->open('ok'), $head,
            $this->t('It is queued for the ERP — I record what you decide, the ERP stays the system of record, so nothing has been sent or changed yet.',
                     'এটা ইআরপির জন্য সারিতে রাখা হলো — আমি কেবল আপনার সিদ্ধান্ত লিখে রাখি, রেকর্ডের মূল জায়গা ইআরপিই, তাই এখনো কিছু পাঠানো বা বদলানো হয়নি।'),
            $this->act('say the word and I will draft the message itself.', 'বললে বার্তাটার খসড়াও করে দেব।')]);
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
