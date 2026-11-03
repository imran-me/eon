<?php
/**
 * EON · Persona — the warmth in the spoken answer.
 *
 * A friendly office assistant does not open every reply with the figure. She
 * says "hmm, let me see…" before a number she had to work out, "ঠিক আছে" before
 * carrying out an order, "তবে একটা কথা —" before bad news, and now and then
 * "আর কিছু?" at the end. This class adds exactly that, and nothing else:
 *
 *   - only to the SPOKEN form; the screen text and its figures are never touched
 *   - only at the edges of the answer (an opener or a closer), so a filler can
 *     never land inside a figure, a name or an account code
 *   - never on a confirmation gate or a refusal: "Say yes and I will post it"
 *     stays exactly as crisp as it was
 *   - deterministic: the choice is seeded by the sentence, so the same answer
 *     sounds the same twice and a test can pin it
 *   - sparse: about two answers in five get an opener, about one in eight a
 *     closer, never both, never the same filler twice in a row
 *   - native: a Bangla answer gets Bangla warmth, never an English "okay"
 *
 * Idempotent: an answer that already opens with one of these is left alone, so
 * the server's `speak` going back through tts.php is not warmed twice.
 */
final class Persona
{
    /** opener kinds → [en, bn] lists */
    private const OPEN = [
        'think' => [
            'en' => ['Hmmm, let me see…', 'Ummm… let me check…', 'Ooh, okay, here it is…', 'Hmm… let me look…', 'Alright, alright…', 'Sooo…', 'Haan, okay…', 'Ummm, right…'],
            'bn' => ['হুমমম, দেখছি…', 'উমমম… দাঁড়াও, দেখি…', 'আচ্ছা আচ্ছা, এই যে…', 'হুম… জি, দেখছি…', 'ওহ, আচ্ছা…', 'হুমম…', 'আহা, এই তো…', 'উম… দেখি তো…'],
        ],
        'ack' => [
            'en' => ['Got it!', 'Okay, done!', 'Sure thing!', 'Right away!', 'Yay, done.'],
            'bn' => ['ঠিক আছে!', 'জি, অবশ্যই!', 'আচ্ছা, হয়ে গেল!', 'এই তো, করে দিলাম!', 'হয়ে গেছে!'],
        ],
        'soft' => [
            'en' => ['Hmmm, one thing —', 'Ummm, heads up —', 'Oh, so —', 'Hmm… hmm…', 'Haaa, well —'],
            'bn' => ['হুমম, তবে একটা কথা —', 'উমম, একটু খেয়াল করো —', 'আচ্ছা, একটা ব্যাপার —', 'তাই তো…', 'হায় হায়, শোনো —'],
        ],
    ];

    private const CLOSE = [
        'en' => ['Anything else?', 'Want me to dig deeper?', 'What next, hmm?'],
        'bn' => ['আর কিছু লাগবে?', 'আর কিছু?', 'আর কী জানতে চাও, হুম?'],
    ];

    /** a confirmation gate or a refusal — said crisply, no warmth added */
    private const GATE = '/\b(say yes|shall i|should i|do you want me|confirm|cannot|can\'t|could not|unable|not allowed|refus|sign in|i do not know|i don\'t know|not sure)\b|হ্যাঁ বল|নিশ্চিত কর|করব কি|পোস্ট করব|পারছি না|পারব না|পারিনি|জানি না|সাইন ইন|অনুমতি নেই/iu';

    /** an order carried out */
    private const DONE = '/\b(done|posted|assigned|created|sent|saved|recorded|opened|opening|marked)\b|সম্পন্ন|করা হয়েছে|দেওয়া হয়েছে|পাঠানো হয়েছে|পাঠিয়েছি|দিয়েছি|খুলছি|খুলে দিলাম|রাখা হয়েছে/iu';

    /** news the boss will not like */
    private const BAD = '/\b(overdue|loss|losing|short|shortfall|warning|risk|at risk|behind|late|absent|anomal|deficit|negative|unpaid|not journalised|gap)\b|বকেয়া|লোকসান|ক্ষতি|ঝুঁকি|ঘাটতি|সতর্ক|দেরি|অনুপস্থিত|গরমিল|পার হয়ে|বাকি/iu';

    /** last filler used, so two answers in a row never open the same way */
    private static array $last = ['open' => null, 'close' => null];

    public static function reset(): void { self::$last = ['open' => null, 'close' => null]; }

    /** every filler, for idempotency checks and tests */
    public static function all(): array
    {
        $out = [];
        foreach (self::OPEN as $k) foreach ($k as $l) foreach ($l as $f) $out[] = $f;
        foreach (self::CLOSE as $l) foreach ($l as $f) $out[] = $f;
        return $out;
    }

    /** which kind of answer this is: gate | ack | soft | think */
    public static function kind(string $t): string
    {
        if (preg_match(self::GATE, $t) || preg_match('/[?？]\s*$/u', $t)) return 'gate';
        $first = preg_split('/(?<=[।.!?])\s+/u', $t, 2)[0] ?? $t;
        if (preg_match(self::DONE, $first)) return 'ack';
        if (preg_match(self::BAD, $t)) return 'soft';
        return 'think';
    }

    /**
     * Spoken answer → the same answer, said the way a person says it.
     * $lang null → decided from the script.
     */
    public static function humanize(string $t, ?string $lang = null): string
    {
        $t = trim($t);
        if ($t === '' || mb_strlen($t) < 12) return $t;
        $lang = $lang === 'bn' || $lang === 'en' ? $lang : self::langOf($t);

        foreach (self::all() as $f) {
            if (mb_strpos($t, $f) === 0 || mb_substr($t, -mb_strlen($f)) === $f) return $t;   // already warmed
        }

        $t = self::pace($t, $lang);
        $kind = self::kind($t);
        if ($kind === 'gate') return $t;

        $h = crc32($t);
        $roll = $h % 20;
        // the answer already opens with its own lead-in ("Short version:",
        // "Good news:", "একটা বিষয় বলি,") — a second one would stack
        $hasLead = (bool) preg_match('/^[^.:।,—…]{1,32}[:,—]/u', $t);
        if ($roll < 13 && !$hasLead) {                      // ~40%: an opener
            $list = self::OPEN[$kind][$lang];
            $f = self::pick($list, intdiv($h, 20), 'open');
            return $f . ' ' . $t;
        }
        if ($roll < 16 && $kind !== 'ack') {               // ~15%: a closer, never after an order
            $f = self::pick(self::CLOSE[$lang], intdiv($h, 20), 'close');
            $end = preg_match('/[।.!?…]$/u', $t) ? '' : ($lang === 'bn' ? '।' : '.');
            return $t . $end . ' ' . $f;
        }
        return $t;
    }

    private static function pick(array $list, int $seed, string $slot): string
    {
        $i = $seed % count($list);
        if ($list[$i] === self::$last[$slot]) $i = ($i + 1) % count($list);
        return self::$last[$slot] = $list[$i];
    }

    /**
     * Pauses a speech engine can hear: a semicolon becomes a full stop, and a
     * sentence that runs past ~200 characters is broken at its last comma
     * before that point. Figures are never split — the break is at ", ".
     */
    public static function pace(string $t, string $lang): string
    {
        $stop = $lang === 'bn' ? '।' : '.';
        $t = preg_replace('/\s*;\s+/u', $stop . ' ', $t) ?? $t;
        $parts = preg_split('/(?<=[।.!?])\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [$t];
        foreach ($parts as &$p) {
            if (mb_strlen($p) <= 200) continue;
            $cut = mb_strrpos(mb_substr($p, 0, 200), ', ');
            if ($cut === false || $cut < 60) continue;
            $p = mb_substr($p, 0, $cut) . $stop . ' ' . self::cap(mb_substr($p, $cut + 2));
        }
        unset($p);
        return implode(' ', $parts);
    }

    private static function cap(string $s): string
    {
        return preg_match('/^[a-z]/', $s) ? strtoupper($s[0]) . substr($s, 1) : $s;
    }

    private static function langOf(string $t): string
    {
        $bn = preg_match_all('/[\x{0980}-\x{09FF}]/u', $t);
        $lat = preg_match_all('/[A-Za-z]/', $t);
        return $bn > $lat * 0.3 ? 'bn' : 'en';
    }

    /**
     * SSML body for providers that speak it (Azure). The text is escaped first;
     * then an ellipsis becomes a short breath and an em dash a slightly longer one.
     */
    public static function ssml(string $text): string
    {
        $x = htmlspecialchars($text, ENT_QUOTES | ENT_XML1);
        $x = str_replace('…', '<break time="280ms"/>', $x);
        $x = preg_replace('/\s—\s/u', ' <break time="200ms"/> ', $x) ?? $x;
        return $x;
    }
}
