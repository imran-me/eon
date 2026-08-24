<?php
declare(strict_types=1);

/* ============================================================
   Think — EON reasoning out loud, from what it knows.

   `KbCorpus` finds the one record that best matches a question
   and prints its paragraph. That is retrieval, and retrieval is
   not what the boss experiences when he asks a person something:
   a person says what you are asking about, then what that thing
   is, then how it works, then the part that catches people out,
   then what he would do. The facts are the same; the difference
   is that they arrive as an argument instead of an extract.

   So this layer plans before it speaks. It picks a focus — one
   trade, one module, one subject — pulls the facets that answer
   *the kind of question that was asked*, orders them the way a
   case is made, and joins them with the words that carry an
   argument along. Nothing is generated: every clause is a stored
   fact, and the chain it was placed in is returned alongside the
   text so a test can assert the reasoning, not just the wording.

   Three rules it does not bend:

   - **The corpus is not the books.** It knows what bed occupancy
     means in any hospital; it does not know yours. Every answer
     says which it is speaking from, because a general fact read
     as a company figure is the worst thing this system can do.
   - **A বাংলা answer is বাংলা throughout.** The corpus has no
     Bangla `remedy`, `process_step` or `comparison` records at
     all, so a Bangla plan cannot fill those moves. It says the
     fix is only written up in English and offers it, rather than
     splicing an English paragraph into a Bangla sentence — the
     leak this codebase has paid for more than once.
   - **Same question, same answer.** The phrasing varies between
     *different* questions so it does not read like a form letter,
     but it is chosen by a hash of the question, never at random.
     A demo that answers differently the second time is a demo
     nobody trusts.
   ============================================================ */
final class Think
{
    /** below this the corpus is guessing, and an honest miss beats a confident essay */
    private const MIN_SCORE = 0.55;

    /** how many facts an argument may carry before it stops being one */
    private const MAX_MOVES = 5;

    /** the moves each kind of question is answered in, in the order an argument runs */
    private const PLANS = [
        // "what is X", "X মানে কী"
        'define'  => ['glossary', 'entity', 'module_overview', 'entity_fields', 'relationship', 'pitfall'],
        // "how does X work", "X কীভাবে হয়"
        'how'     => ['module_overview', 'process', 'process_step', 'entity_lifecycle', 'posting_rule', 'pitfall'],
        // "what is bed occupancy", "how do I read it", "what moves it"
        'measure' => ['kpi_definition', 'kpi_reading', 'kpi_action', 'pitfall', 'remedy'],
        // "what goes wrong with X", "X-এ সমস্যা কী"
        'trouble' => ['pitfall', 'remedy', 'answering_note', 'process'],
        // "who can approve X"
        'who'     => ['permission', 'role_matrix', 'vertical_roles', 'pitfall'],
        // "how does X post", "X কীভাবে খতিয়ানে বসে"
        'post'    => ['posting_rule', 'integration', 'entity', 'pitfall'],
        // "should we…", "what do I do about X"
        'decide'  => ['scenario', 'kpi_action', 'remedy', 'pitfall'],
    ];

    /** facets the corpus simply does not carry in বাংলা — never silently dropped */
    private const EN_ONLY = ['remedy', 'process_step', 'comparison', 'field', 'vertical_kpi',
                             'vertical_roles', 'compliance'];

    /**
     * Reason about a question.
     *
     * @return array{text:string,lang:string,chain:array,focus:array,score:float,gaps:array}|null
     */
    public static function about(string $q, ?string $lang = null, array $opts = []): ?array
    {
        if (!class_exists('KbCorpus') || !KbCorpus::available()) return null;

        $lang = $lang ?: KbCorpus::langOf($q);
        $vertical = KbCorpus::detectVertical($q) ?? KbCorpus::defaultVertical();

        /* The subject is read out of the question, not off the top of a ranking.
           Fulltext cannot decide it: every hospital `vertical_kpi` record names
           bed occupancy while listing what to read beside the others, so all five
           scored identically and the one actually *about* bed occupancy did not
           come first. Ranking is a good way to find candidates and a poor way to
           decide what was asked — which showed up as EON answering "how does
           admissions work" with a lecture on users and permissions. So a subject
           or a module the question names outright pins the focus, and the ranking
           is only consulted when it names neither. */
        $named = KbCorpus::detectVertical($q) !== null;
        $pin = self::pin($q, $lang, $vertical);

        /* No trade was named and the default one does not recognise the subject —
           so look across all of them before giving up. "What is bed occupancy",
           asked with no hospital in the sentence, was being answered as a travel
           agency because travel is the configured default, and the nearest thing
           travel had was a disputed memo. */
        if (!$named && $pin['subject'] === null && $pin['module'] === null) {
            $pin = self::pin($q, $lang, null);
            if ($pin['vertical'] !== null) $vertical = $pin['vertical'];
        }

        /* If the question names nothing this corpus holds, EON does not know it.
           The ranking used to be allowed to answer on its own, and it always
           found *something*: "what is the capital of Bangladesh" came back as a
           lecture on capitalising a purchase, and "sing me a song" on chasing
           overdue money. A retrieval score is not knowledge of the subject. */
        if ($pin['subject'] === null && $pin['module'] === null) return null;

        $hits = KbCorpus::search($q, $opts + ['lang' => $lang, 'vertical' => $vertical, 'limit' => 8]);
        $top = null;
        foreach ($hits as $h) {
            if ($pin['subject'] !== null && ($h['subject'] ?? null) === $pin['subject']) { $top = $h; break; }
            if ($top === null && $pin['module'] !== null && ($h['module'] ?? null) === $pin['module']) $top = $h;
        }
        $top ??= ['facet' => 'module_overview', 'subject' => $pin['subject'],
                  'module' => $pin['module'], 'vertical' => $vertical,
                  'title' => '', 'lang' => $lang, 'score' => 1.0];

        $subject = self::focus($q, $top, $lang);
        $label = self::label($subject, $lang);
        // a বাংলা question gets the বাংলা name the question itself used
        if ($lang === 'bn' && ($pin['bn_label'] ?? null) !== null
            && ($pin['subject'] === null || $subject === $pin['subject'])) {
            $label = (string) $pin['bn_label'];
        }
        $focus = ['vertical' => $top['vertical'], 'module' => $top['module'],
                  'subject' => $top['subject'], 'label' => $label,
                  'title' => $top['title']];
        $kind = self::kindOf($q, $lang, (string) $top['facet']);

        $facts = self::gather($focus, $lang, self::PLANS[$kind], $top);
        if (count($facts) < 2) return null;              // one fact is a lookup, not an argument

        [$text, $chain, $gaps] = self::speak($q, $kind, $focus, $facts, $lang);
        if ($text === '') return null;

        return ['text' => $text, 'lang' => $lang, 'chain' => $chain, 'focus' => $focus,
                'kind' => $kind, 'score' => (float) $top['score'], 'gaps' => $gaps];
    }

    /**
     * The subject and module the question names outright.
     *
     * Every distinct subject the corpus holds for this trade is compared against
     * the sentence, longest first, so "revenue per bed day" beats "bed" and a
     * question that says nothing recognisable pins nothing at all. The list is
     * a few thousand strings per trade and is read once per process.
     */
    private static function pin(string $q, string $lang, ?string $vertical): array
    {
        $n = ' ' . mb_strtolower(trim($q)) . ' ';
        $out = ['subject' => null, 'module' => null, 'vertical' => null, 'bn_label' => null];

        static $cache = [];
        $key = $lang . '|' . ($vertical ?? '*');
        if (!isset($cache[$key])) {
            $cache[$key] = [];
            try {
                $pdo = Db::eon();
                if ($vertical === null) {
                    $st = $pdo->prepare('SELECT DISTINCT subject, module, vertical, title FROM eon_kb
                                          WHERE lang = ? AND subject IS NOT NULL AND subject <> \'\'');
                    $st->execute([$lang]);
                } else {
                    $st = $pdo->prepare('SELECT DISTINCT subject, module, vertical, title FROM eon_kb
                                          WHERE lang = ? AND (vertical = ? OR vertical IS NULL)
                                            AND subject IS NOT NULL AND subject <> \'\'');
                    $st->execute([$lang, $vertical]);
                }
                $cache[$key] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) { $cache[$key] = []; }
        }

        $bestLen = 0;
        /* The Bangla run that matched is the only Bangla name this subject has —
           the `subject` column stores English even on বাংলা rows — so it is kept
           and used as the label. Without it the frame opened "আপনি জানতে চাইছেন
           bed occupancy নিয়ে": an English subject inside a Bangla sentence, in the
           one clause whose whole job is to prove EON understood the question. */
        $take = function (array $r, int $len, string $bnLabel = '') use (&$out, &$bestLen): void {
            if ($len <= $bestLen) return;
            $bestLen = $len;
            $out['subject'] = (string) $r['subject'];
            $out['module'] = $r['module'] !== null ? (string) $r['module'] : null;
            $out['vertical'] = $r['vertical'] !== null ? (string) $r['vertical'] : null;
            $out['bn_label'] = $bnLabel !== '' ? $bnLabel : null;
        };

        foreach ($cache[$key] as $r) {
            $s = (string) $r['subject'];
            if ($s === '' || mb_strpos($s, '~') !== false) continue;
            $probe = mb_strtolower(str_replace('_', ' ', $s));
            if (mb_strlen($probe) >= 4 && mb_strpos($n, $probe) !== false) {   // "bed", "gl" — too small to bet on
                $take($r, mb_strlen($probe));
                continue;
            }
            /* বাংলা rows carry the English subject — `subject: k.en` — so a Bangla
               question can never match on it, and "হাসপাতালে বেড দখল মানে কী" pinned
               nothing and was answered about users and permissions. The Bangla name
               only exists inside the title, so the title is where it is looked for:
               the longest run of its words that the question actually contains. */
            if ($lang === 'bn') {
                $hit = self::bnRun((string) ($r['title'] ?? ''), $n);
                if ($hit !== '') $take($r, mb_strlen($hit), $hit);
            }
        }

        // a module named in the sentence beats a module inferred from the subject
        $named = KbCorpus::detectModule($q, $vertical);
        if ($named !== null) {
            if ($out['module'] !== null && $out['module'] !== $named) $out['subject'] = null;
            $out['module'] = $named;
        }
        return $out;
    }

    /**
     * The longest run of Bangla words from a title that the question contains,
     * measured in characters. Two words is the floor: one Bangla word out of a
     * title is a coincidence, not a subject.
     */
    private static function bnRun(string $title, string $n): string
    {
        $title = trim(preg_replace('/[^\p{L}\p{M}\p{N}\s]+/u', ' ', mb_strtolower($title)) ?? '');
        if ($title === '') return '';
        $w = array_values(array_filter(preg_split('/\s+/u', $title) ?: [], fn($x) => mb_strlen($x) >= 2));
        $count = count($w);
        if ($count < 2) return '';
        for ($len = min($count, 5); $len >= 2; $len--) {
            for ($i = 0; $i + $len <= $count; $i++) {
                $run = implode(' ', array_slice($w, $i, $len));
                if (mb_strlen($run) >= 6 && mb_strpos($n, $run) !== false) return $run;
            }
        }
        return '';
    }

    /* ---------------- what kind of question is this? ---------------- */

    private static function kindOf(string $q, string $lang, string $topFacet): string
    {
        $n = ' ' . mb_strtolower(trim($q)) . ' ';

        $has = function (array $cues) use ($n): bool {
            foreach ($cues as $c) {
                if (preg_match('/^[a-z0-9 \'-]+$/', $c)) {
                    if (preg_match('/(?<![a-z0-9])' . preg_quote($c, '/') . '(?![a-z0-9])/u', $n)) return true;
                } elseif (mb_strpos($n, $c) !== false) return true;
            }
            return false;
        };

        // the sentence's own shape wins; the top facet only breaks a tie
        if ($has(['who can', 'who may', 'who approves', 'who is allowed', 'permission', 'rights',
                  'কে অনুমোদন', 'কার অনুমতি', 'কে করতে পারে', 'অনুমতি'])) return 'who';
        if ($has(['post', 'posts', 'posting', 'journal', 'ledger', 'debit', 'credit', 'double entry',
                  'খতিয়ান', 'জার্নাল', 'ডেবিট', 'ক্রেডিট'])) return 'post';
        if ($has(['goes wrong', 'go wrong', 'problem', 'problems', 'trap', 'traps', 'mistake',
                  'mistakes', 'risk', 'risks', 'watch out', 'সমস্যা', 'ভুল', 'ঝুঁকি', 'ফাঁদ'])) return 'trouble';
        if ($has(['should i', 'should we', 'what do i do', 'what should', 'how do i fix', 'how to fix',
                  'কী করব', 'কি করব', 'করা উচিত', 'কীভাবে ঠিক'])) return 'decide';
        if ($has(['how does', 'how do', 'how is', 'how are', 'walk me', 'step', 'steps', 'process',
                  'workflow', 'কীভাবে', 'কিভাবে', 'ধাপ', 'প্রক্রিয়া'])) return 'how';
        if ($has(['rate', 'ratio', 'occupancy', 'margin', 'per ', 'measure', 'metric', 'kpi',
                  'how do i read', 'হার', 'অনুপাত', 'পরিমাপ'])) return 'measure';

        // no shape in the sentence — trust what the corpus matched
        return match ($topFacet) {
            'kpi_definition', 'kpi_reading', 'kpi_action', 'vertical_kpi' => 'measure',
            'process', 'process_step', 'entity_lifecycle'                 => 'how',
            'pitfall', 'remedy', 'answering_note'                         => 'trouble',
            'permission', 'role_matrix', 'vertical_roles'                 => 'who',
            'posting_rule', 'integration'                                 => 'post',
            'scenario'                                                    => 'decide',
            default                                                       => 'define',
        };
    }

    /* ---------------- pull the facts the plan asks for ---------------- */

    /**
     * One record per move, all about the same subject where the corpus has one,
     * falling back to the same module. A chain built from three different
     * subjects is not reasoning, it is a search result with conjunctions.
     */
    private static function gather(array $focus, string $lang, array $plan, array $top): array
    {
        try { $pdo = Db::eon(); } catch (Throwable $e) { return []; }

        $out = [];
        foreach ($plan as $facet) {
            if ($lang === 'bn' && in_array($facet, self::EN_ONLY, true)) { $out[$facet] = null; continue; }

            $row = null;
            // the subject first — the same thing the question named
            if (($focus['subject'] ?? null) !== null) {
                $st = $pdo->prepare('SELECT id, facet, subject, title, body, meta FROM eon_kb
                                      WHERE lang = ? AND facet = ? AND subject = ?
                                        AND (vertical = ? OR vertical IS NULL) LIMIT 1');
                $st->execute([$lang, $facet, $focus['subject'], $focus['vertical']]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            // then the module it lives in
            if ($row === null && ($focus['module'] ?? null) !== null) {
                $st = $pdo->prepare('SELECT id, facet, subject, title, body, meta FROM eon_kb
                                      WHERE lang = ? AND facet = ? AND module = ?
                                        AND (vertical = ? OR vertical IS NULL) LIMIT 1');
                $st->execute([$lang, $facet, $focus['module'], $focus['vertical']]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            $out[$facet] = $row;
        }

        // the record that actually matched belongs in the chain even if its facet was not planned
        if (!isset($out[$top['facet']]) || $out[$top['facet']] === null) {
            if (($top['lang'] ?? $lang) === $lang) $out[$top['facet']] = $top;
        }
        return $out;
    }

    /* ---------------- say it as an argument ---------------- */

    private static function speak(string $q, string $kind, array $focus, array $facts, string $lang): array
    {
        $bn = $lang === 'bn';
        $seed = abs(crc32($q));
        $pick = fn(array $o) => $o[$seed % count($o)];

        $vName = self::verticalName((string) $focus['vertical'], $lang);
        $subject = trim((string) ($focus['label'] ?? ''));

        $parts = [];
        $chain = [];
        $gaps = [];

        /* --- the frame: say back what is being answered, and in which trade ---
           An answer with no scope on it is read as universal, and the same
           sentence has twenty-four different answers in this corpus. */
        if ($subject !== '') {
            $parts[] = $bn
                ? sprintf($pick(['%s নিয়ে বলি — %s-এর প্রেক্ষিতে।', 'আপনি জানতে চাইছেন %s নিয়ে; %s-এ এটা যেভাবে চলে বলছি।',
                                 '%s — %s-এর হিসাবে ধরে নিচ্ছি।']), $subject, $vName)
                : sprintf($pick(['You are asking about %s, and in a %s that means something specific.',
                                 'Let me take %s from the top, as a %s runs it.',
                                 '%s, in a %s — here is how it hangs together.']), $subject, $vName);
        } else {
            $parts[] = $bn ? sprintf('%s-এর প্রেক্ষিতে বলছি।', $vName)
                           : sprintf('Answering this for a %s.', $vName);
        }
        $chain[] = ['move' => 'frame', 'id' => null];

        /* --- the body: one move per fact, joined by the words an argument uses ---

           Two limits, both learned from reading the first output. A chain of eight
           moves is not reasoning, it is a wall — the answer ran to fifteen
           sentences and nobody would hear the end of it spoken aloud. And the
           records overlap: `process` and `process_step` tell the same story, and a
           module's purpose clause is repeated inside the pitfall and the
           integration record, so one phrase came back three times in a single
           answer. A person making a case says each thing once. */
        $first = true;
        $said = [];                          // significant words already spoken
        $bodyMoves = 0;
        foreach ($facts as $facet => $row) {
            if ($row === null) {
                if ($lang === 'bn' && in_array($facet, self::EN_ONLY, true)) $gaps[] = $facet;
                continue;
            }
            if ($bodyMoves >= self::MAX_MOVES) break;
            $lead = self::lead((string) $row['body'], self::sentencesFor($facet));
            if ($lead === '') continue;
            if (self::repeats($lead, $said)) continue;

            $join = $first ? '' : self::connective($facet, $lang, $seed + count($chain));
            $parts[] = ($join === '' ? '' : $join . ' ') . $lead;
            $chain[] = ['move' => $facet, 'id' => $row['id'], 'subject' => $row['subject']];
            foreach (self::words($lead) as $w) $said[$w] = true;
            $first = false;
            $bodyMoves++;

            // a measure with a stored formula is worth saying outright
            if ($facet === 'kpi_definition') {
                $meta = json_decode((string) ($row['meta'] ?? ''), true);
                $f = is_array($meta) ? trim((string) ($meta['formula'] ?? '')) : '';
                // the definition usually quotes the formula already; saying it twice
                // is the tic of something reciting rather than explaining
                if ($f !== '' && mb_strpos(implode(' ', $parts), $f) !== false) $f = '';
                if ($f !== '') {
                    $parts[] = $bn ? sprintf('সূত্রটা হলো %s।', $f) : sprintf('The formula is %s.', $f);
                    $chain[] = ['move' => 'formula', 'id' => $row['id']];
                }
            }
        }

        if (count($chain) < 3) return ['', $chain, $gaps];   // a frame and one fact is not an argument

        /* --- what the corpus could not say in the language asked --- */
        if ($gaps !== []) {
            $parts[] = sprintf('ওই অংশটা — %s — আমার সংগ্রহে এখনো শুধু ইংরেজিতে লেখা আছে। ইংরেজিতে জিজ্ঞেস করলে পুরোটা বলে দেব; বাংলায় বানিয়ে বলব না।',
                self::gapNames($gaps));
            $chain[] = ['move' => 'gap', 'id' => null];
        }

        /* --- the close: this is the trade, not your books --- */
        $parts[] = $bn
            ? $pick(['এটুকু %s মাত্রেই সত্যি — আপনার নিজের হিসাবের সংখ্যা নয়। আপনার বইয়ে এটা কত, জানতে চাইলে বলুন, খতিয়ান থেকে পড়ে দেব।',
                     'এটা %s-এর সাধারণ নিয়ম, আপনার প্রতিষ্ঠানের সংখ্যা নয়। নিজের হিসাবটা চাইলে বলুন — লাইভ ডেটা থেকে বের করে দেব।'])
            : $pick(['That is how it works in any %s — it is not a reading of your books. Ask me for your own figure and I will take it off the ledger.',
                     'All of that is the trade, not your company. Say the word and I will read what your own %s actually shows.']);
        $parts[count($parts) - 1] = sprintf($parts[count($parts) - 1], $vName);
        $chain[] = ['move' => 'close', 'id' => null];

        return [self::join($parts, $lang), $chain, $gaps];
    }

    /** how much of a record to quote — a step list needs more room than a definition */
    private static function sentencesFor(string $facet): int
    {
        return match ($facet) {
            'process', 'process_step', 'entity_lifecycle', 'posting_rule' => 3,
            'kpi_reading', 'kpi_action', 'remedy', 'scenario'             => 2,
            default                                                       => 2,
        };
    }

    /**
     * The words that carry an argument from one clause to the next.
     *
     * Without these the answer is a list of true statements, which is exactly
     * what a search result already was. With them it reads as somebody working
     * through the thing — and the facts underneath have not changed at all.
     */
    private static function connective(string $facet, string $lang, int $seed): string
    {
        $bn = $lang === 'bn';
        $set = match ($facet) {
            'kpi_reading'  => $bn ? ['পড়তে হয় এভাবে —', 'এখন কীভাবে পড়বেন:'] : ['Reading it is the next part.', 'How you read it matters as much:'],
            'kpi_action'   => $bn ? ['তাহলে নড়ানোর উপায় কী —', 'এটা নড়ে কীসে:'] : ['Which means only two things can move it.', 'So what actually moves it:'],
            'pitfall'      => $bn ? ['এখানেই লোকে ঠকে —', 'যে জায়গায় ভুলটা হয়:'] : ['The place this catches people out:', 'Here is where it goes wrong in practice.'],
            'remedy'       => $bn ? ['সারানোর পথ —'] : ['The fix is not complicated:', 'What puts it right:'],
            'posting_rule' => $bn ? ['খতিয়ানে এটা বসে এভাবে —'] : ['In the ledger it lands like this:', 'The posting behind it:'],
            'process',
            'process_step' => $bn ? ['ধাপগুলো এরকম —', 'কাজটা যেভাবে এগোয়:'] : ['The way it actually runs:', 'Step by step:'],
            'entity_fields' => $bn ? ['কী কী লেখা থাকে:'] : ['What gets recorded on it:', 'The fields that matter:'],
            'permission',
            'role_matrix'  => $bn ? ['কে কী করতে পারে —'] : ['Who is allowed to do what:', 'On rights:'],
            'relationship' => $bn ? ['আর এর সাথে জড়িয়ে আছে —'] : ['It does not sit alone, either.', 'And it connects outward:'],
            'scenario'     => $bn ? ['বাস্তবে যা দাঁড়ায় —'] : ['Put concretely:', 'In practice that looks like this:'],
            default        => $bn ? ['তার সাথে —'] : ['On top of that:', 'Alongside that:'],
        };
        return $set[$seed % count($set)];
    }

    /**
     * The leading sentences of a stored body.
     *
     * The split has to require a capital or a Bangla letter after the stop.
     * Splitting on every ". " cut "runs in 5 steps: 1. collect the application;
     * 2. check eligibility; 3." — the list was severed mid-item and the answer
     * trailed off on a bare numeral.
     */
    private static function lead(string $body, int $n): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
        if ($body === '') return '';
        $bits = preg_split('/(?<=[.।])\s+(?=[A-Z\x{0980}-\x{09FF}])/u', $body) ?: [$body];
        $take = array_slice($bits, 0, max(1, $n));
        return trim(implode(' ', $take));
    }

    /** the words worth comparing two clauses on */
    private static function words(string $s): array
    {
        $s = mb_strtolower(preg_replace('/[^\p{L}\p{M}\p{N}\s]+/u', ' ', $s) ?? $s);
        $out = [];
        foreach (preg_split('/\s+/u', trim($s)) ?: [] as $w) {
            if (mb_strlen($w) >= 4) $out[] = $w;
        }
        return $out;
    }

    /** has this already been said, in all but wording? */
    private static function repeats(string $lead, array $said): bool
    {
        $w = self::words($lead);
        if (count($w) < 4) return false;
        $hit = 0;
        foreach ($w as $x) if (isset($said[$x])) $hit++;
        return ($hit / count($w)) > 0.72;
    }

    private static function join(array $parts, string $lang): string
    {
        $parts = array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
        return implode(' ', $parts);
    }

    /**
     * What the question is about — which is not always what the best record is about.
     *
     * The frame has to name the thing the boss said, because the frame is EON
     * saying "I understood you". Taking it from the top hit put words in his
     * mouth twice over: an `integration` record's `subject` is the module at the
     * *far* end of the join, so "how does admissions work" was framed as
     * "you are asking about student_billing"; and once the corpus was rebuilt the
     * best hit became a pitfall and the same question was framed as "you are
     * asking about over admission". Neither is what was asked. So: the subject
     * only wins if the sentence actually mentions it, and otherwise the module —
     * the thing the question named — does.
     */
    private static function focus(string $q, array $top, string $lang): string
    {
        $n = ' ' . mb_strtolower($q) . ' ';
        $subject = trim((string) ($top['subject'] ?? ''));
        $module  = trim((string) ($top['module'] ?? ''));

        $mentions = function (string $s) use ($n): bool {
            $s = trim(mb_strtolower(str_replace('_', ' ', preg_replace('/~.*$/', '', $s) ?? $s)));
            if ($s === '' || mb_strlen($s) < 3) return false;
            /* The whole phrase, not a word of it. Matching any long word let the
               pitfall "over_admission" claim a question that said "admissions" —
               half a subject is a different subject, and the frame would then
               tell the boss he asked something he did not. A trailing s is the
               only latitude worth allowing. */
            return mb_strpos($n, $s) !== false || mb_strpos($n, $s . 's') !== false;
        };

        if ($subject !== ''
            && !in_array($top['facet'], ['integration', 'relationship'], true)
            && $mentions($subject)) {
            return $subject;
        }
        if ($module !== '') {
            // the module's own name, and the names people call it by
            $mods = KbCorpus::vocab()['modules'] ?? [];
            $names = [$module];
            if (isset($mods[$module])) {
                $names[] = (string) $mods[$module]['en'];
                $names[] = (string) $mods[$module]['bn'];
                foreach ((array) ($mods[$module]['aliases'] ?? []) as $a) $names[] = (string) $a;
            }
            foreach ($names as $nm) if ($mentions($nm)) return $module;
        }
        return $subject !== '' ? $subject : $module;
    }

    /**
     * What to call the thing being asked about.
     *
     * Subjects arrive as they are stored — a module id like `student_billing`, or
     * a relationship key like `role~permission`. Printing either at the boss reads
     * as a database leaking through the answer, so the module vocabulary is
     * consulted first and only then is the raw string tidied.
     */
    private static function label(string $subject, string $lang): string
    {
        $subject = trim($subject);
        if ($subject === '') return '';
        $subject = preg_replace('/~.*$/', '', $subject) ?? $subject;

        $mods = KbCorpus::vocab()['modules'] ?? [];
        if (isset($mods[$subject])) {
            return (string) ($lang === 'bn' ? $mods[$subject]['bn'] : $mods[$subject]['en']);
        }
        return trim(str_replace('_', ' ', $subject));
    }

    private static function verticalName(string $id, string $lang): string
    {
        foreach (KbCorpus::vocab()['verticals'] as $v) {
            if (($v['id'] ?? '') === $id) return (string) ($lang === 'bn' ? $v['bn'] : $v['en']);
        }
        return $lang === 'bn' ? 'এই ব্যবসা' : 'business of this kind';
    }

    private static function gapNames(array $gaps): string
    {
        $bn = ['remedy' => 'সমাধান', 'process_step' => 'ধাপে ধাপে বর্ণনা', 'comparison' => 'তুলনা',
               'field' => 'তথ্যক্ষেত্রের বিবরণ', 'vertical_kpi' => 'প্রধান পরিমাপ',
               'vertical_roles' => 'ভূমিকার তালিকা', 'compliance' => 'সম্মতির নিয়ম'];
        $out = [];
        foreach (array_unique($gaps) as $g) $out[] = $bn[$g] ?? $g;
        return implode(', ', $out);
    }
}
