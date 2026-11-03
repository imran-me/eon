<?php
declare(strict_types=1);

/* ============================================================
   AccountsBook — how the Epal ERP keeps its books, said back.

   server/kb/accounts-book.json holds one entry per accounting
   event or rule: the journal legs the ERP really posts, which
   ledger a party lives in, how a statement is read, and the data
   traps found in the production books. Every 'epal' entry names
   the ERP file or the dump rows it was taken from; 'general'
   entries are textbook bookkeeping and say so.

   This is retrieval, not reasoning: the question is matched
   against each entry's cues in English, বাংলা and Banglish, and
   the best entry is said back in the language asked. Two doors:

     strong()  a bookkeeping question asked as one — a how/why/
               what form AND a bookkeeping word (journal, entry,
               debit, posted, এন্ট্রি, জার্নাল …) AND a whole cue
               present in the sentence. Brain asks this before the
               figure answerers, because "how is a ticket sale
               posted" is not a request for this month's sales.
     answer()  the best entry above the floor, used when the
               layers above gave only a weak answer.

   A rate is never stated: the ERP has no tax-rate table, so no
   entry carries one.
   ============================================================ */

final class AccountsBook
{
    /** below this the match is a shared word, not the subject */
    private const MIN_SCORE = 4.0;

    private static ?array $entries = null;

    /** the book, cues pre-normalised the way questions are */
    public static function entries(): array
    {
        if (self::$entries !== null) return self::$entries;
        $file = dirname(__DIR__) . '/kb/accounts-book.json';
        $raw = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $out = [];
        foreach (($raw['entries'] ?? []) as $e) {
            $cues = [];
            foreach (['en', 'bn', 'bl'] as $script) {
                foreach (($e['cues'][$script] ?? []) as $c) {
                    $n = self::norm((string) $c);
                    if ($n !== '') $cues[] = $n;
                }
            }
            $e['_cues'] = array_values(array_unique($cues));
            $out[] = $e;
        }
        return self::$entries = $out;
    }

    public static function available(): bool
    {
        return self::entries() !== [];
    }

    /** the same folding Nlu applies, so a cue written কী still meets a question folded to কি */
    public static function norm(string $s): string
    {
        if (class_exists('Nlu')) return Nlu::norm($s);
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = preg_replace('/[।?!,;:"\'()\[\]]+/u', ' ', $s);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $s));
    }

    /** words of a normalised string; Bangla words keep their vowel signs (\p{M}) */
    private static function words(string $n): array
    {
        $w = preg_split('/[^\p{L}\p{M}\p{N}&]+/u', $n, -1, PREG_SPLIT_NO_EMPTY);
        return $w ?: [];
    }

    /** a cue present as a whole phrase — word-bounded on both sides, in either script.
        `\b` never fires after a Bangla letter, so the boundary is spelled out. */
    private static function hasPhrase(string $hay, string $cue): bool
    {
        $re = '/(?<![\p{L}\p{M}\p{N}])' . preg_quote($cue, '/') . '(?![\p{L}\p{M}\p{N}])/u';
        return (bool) preg_match($re, $hay);
    }

    /** a Bangla word met with a case ending still counts (জার্নালে, এন্ট্রির) */
    private static function wordIn(string $w, array $qWords): bool
    {
        foreach ($qWords as $q) {
            if ($q === $w) return true;
            if (mb_strlen($w) >= 3 && preg_match('/[\x{0980}-\x{09FF}]/u', $w)
                && mb_strpos($q, $w) === 0 && mb_strlen($q) - mb_strlen($w) <= 3) return true;
        }
        return false;
    }

    /** words that carry no subject — never enough to choose an entry on */
    private const STOP = ['how', 'is', 'a', 'an', 'the', 'what', 'why', 'does', 'do', 'of', 'to', 'in', 'for', 'and',
        'on', 'it', 'when', 'which', 'where', 'are', 'be', 'by', 'we', 'our', 'from', 'with', 'who', 'vs', 'me', 'tell',
        'কি', 'কিভাবে', 'কীভাবে', 'কেন', 'হয়', 'হয়', 'কোন', 'আর', 'এর', 'মানে', 'ki', 'kivabe', 'keno', 'hoy', 'er', 'kon'];

    /** score every entry; [entry, score, exact] best first */
    public static function rank(string $q): array
    {
        $n = self::norm($q);
        if ($n === '') return [];
        $qWords = self::words($n);
        $rows = [];
        foreach (self::entries() as $e) {
            $best = 0.0; $exact = false;
            foreach ($e['_cues'] as $cue) {
                $cw = array_values(array_diff(self::words($cue), self::STOP));
                if ($cw === []) continue;
                if (self::hasPhrase($n, $cue)) {
                    $s = 3.0 + 2.0 * count($cw);
                    if ($s > $best) { $best = $s; }
                    $exact = true;
                    continue;
                }
                $hit = 0;
                foreach ($cw as $w) if (self::wordIn($w, $qWords)) $hit++;
                /* a partial cue needs most of its content words and at least two of them */
                if ($hit >= 2 && $hit >= count($cw) - 1) {
                    $s = 1.5 * $hit + ($hit === count($cw) ? 1.0 : 0.0);
                    if ($s > $best) $best = $s;
                }
            }
            if ($best > 0) $rows[] = ['entry' => $e, 'score' => $best, 'exact' => $exact];
        }
        usort($rows, fn($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['entry']['id'], $b['entry']['id']));
        return $rows;
    }

    /** a how / why / what / which question, in any of the three scripts */
    private static function asksHowWhy(string $n): bool
    {
        return (bool) preg_match('/\b(how|why|what|which|where|explain|meaning|mean|means|happens)\b/u', $n)
            || (bool) preg_match('/(কিভাবে|কীভাবে|কেন|কি(?![\x{0980}-\x{09FF}])|কোন|কোথায়|কোথায়|মানে|বুঝিয়ে|ব্যাখ্যা)/u', $n)
            || (bool) preg_match('/\b(kivabe|keno|ki|kon|kothay|mane)\b/u', $n);
    }

    /** the word that makes it a bookkeeping question rather than a request for a figure */
    private static function bookWord(string $n): bool
    {
        return (bool) preg_match('/\b(entry|entries|journal|journals|posted|posting|debit|credit|double entry|chart of accounts|accounting|ledger|opening balance|contra|reversal|allocated|unallocated|receivable|payable|trial balance|balance sheet|profit and loss)\b/u', $n)
            || (bool) preg_match('/(এন্ট্রি|জার্নাল|পোস্ট|ডেবিট|ক্রেডিট|খতিয়ান|লেজার|প্রারম্ভিক জের|দুতরফা|পাওনা|দেনা|অগ্রিম|ট্রায়াল ব্যালান্স|রেওয়ামিল|ব্যালান্স শিট|লাভ-ক্ষতি|হিসাবের তালিকা)/u', $n)
            || (bool) preg_match('/\b(entry|journal|post|debit|credit|ledger|hisab)\b/u', $n);
    }

    /** strong enough to answer ahead of the figure answerers */
    public static function strong(string $q, ?string $lang = null): ?array
    {
        $n = self::norm($q);
        if (!self::asksHowWhy($n) || !self::bookWord($n)) return null;
        $r = self::rank($q);
        if (!$r || !$r[0]['exact'] || $r[0]['score'] < 7.0) return null;
        /* two entries tied on an exact cue are two subjects — let the figure layers try */
        if (isset($r[1]) && $r[1]['exact'] && $r[1]['score'] >= $r[0]['score']) return null;
        return self::say($r[0], $q, $lang);
    }

    /** the best entry above the floor, or null */
    public static function answer(string $q, ?string $lang = null): ?array
    {
        $r = self::rank($q);
        if (!$r || $r[0]['score'] < self::MIN_SCORE) return null;
        return self::say($r[0], $q, $lang);
    }

    private static function say(array $row, string $q, ?string $lang): array
    {
        $e = $row['entry'];
        $l = class_exists('Nlu') ? Nlu::detectLang($q, $lang) : (preg_match('/[\x{0980}-\x{09FF}]/u', $q) ? 'bn' : ($lang === 'bn' ? 'bn' : 'en'));
        if ($l === 'bn') {
            $text = (string) $e['answer_bn'];
        } else {
            $text = (string) $e['answer_en'];
            if (!empty($e['where']['screen'])) $text .= ' Where in the ERP: ' . $e['where']['screen'] . '.';
        }
        return ['text' => $text, 'lang' => $l, 'id' => $e['id'], 'scope' => $e['scope'] ?? 'epal',
                'topic' => $e['topic'], 'score' => $row['score'], 'source' => $e['source'] ?? [],
                'legs' => $e['legs'] ?? [], 'where' => $e['where'] ?? null];
    }
}
